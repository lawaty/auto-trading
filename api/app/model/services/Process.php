<?php

class Process
{
    private string $process_name;
    const FOREGROUND = 0;
    const BACKGROUND = 1;
    private string $os;
    private int $pid;
    private string $log_path;
    private $log;
    private array $args = [];
    private static bool $inited = false;

    public function getLogger()
    {
        if (!isset($this->log))
            $this->log = fopen($this->log_path, "a");

        return $this->log;
    }

    public function log(string $something)
    {
        if (strpos($something, "\r") !== false) {
            $lines = explode("\r", $something);
            $something = end($lines); // Get the last part after the last \r
            $this->truncateLastLine();
        }

        fwrite($this->getLogger(), "$something — at " . ((new Ndate)->format(Ndate::DATE_TIME)) . "\n");
    }

    private function truncateLastLine()
    {
        // Read current contents and remove the last line
        $contents = file_get_contents($this->log_path);
        $lines = explode("\n", rtrim($contents, "\n"));
        array_pop($lines); // Remove the last line
        file_put_contents($this->log_path, implode("\n", $lines) . "\n");
    }

    private static function init()
    {
        if (!is_dir(LOG_DIR . '/processes'))
            mkdir(LOG_DIR . '/processes');

        self::$inited = true;
    }

    public function __construct(string $process_name, string $log = null)
    {
        if (!self::$inited)
            self::init();

        if (str_contains($process_name, '.php'))
            $process_name = explode('.php', $process_name)[0];

        $this->process_name = $process_name;
        $this->log_path = $log ?? LOG_DIR . "/processes/$this->process_name-" . (new Ndate)->format() . ".log";

        $this->os = $_ENV['OS'];
        $this->getLogger();
    }

    public static function getProcess(int $pid): ?Process
    {
        exec("ps -p $pid -o cmd=", $output, $returnVar);

        if ($returnVar === 0) {
            $cmd = isset($output[0]) ? trim($output[0]) : null;
            $process_name = basename($cmd);
            $process_name = explode('.php', $process_name)[0];
            $process = new self($process_name);
            $process->pid = $pid;
            return $process;
        }
        return null;
    }

    public static function getAllByName(string $name): array
    {
        /**
         * @return array<Process>
         */
        $output = [];
        exec("ps aux | grep '$name' | grep -v grep", $output);

        $pids = [];
        foreach ($output as $line) {
            $columns = preg_split('/\s+/', $line);
            $pids[] = $columns[1];
        }

        $processes = [];
        foreach ($pids as $pid) {
            $temp = new self($name);
            $temp->pid = $pid;
            $processes[] = $temp;
        }

        return $processes;
    }

    public function getPID(): int
    {
        return $this->pid ?? 0;
    }

    public function setLogger(string $path): void
    {
        if (!$this->log = fopen($path, "a"))
            throw new Exception("Cannot Set Logger at path $path");

        $this->log_path = $path;
    }

    public function passArgs(array $args, bool $is_json = false): void
    {
        if ($is_json)
            $this->args = [json_encode($args)];
        else $this->args = $args;
    }

    public function run(int $run_type = Process::FOREGROUND)
    {
        $process_name = $this->process_name;
        $process_path =  APP_DIR . "/processes/$process_name.php";

        if (!file_exists($process_path)) {
            $this->log("The file $process_path does not exist");
            throw new ProcessNotFound($process_name);
        }

        try {
            if ($run_type == static::FOREGROUND) {
                try {
                    exec("php $process_path " . implode(" ", $this->args), $outputArray, $returnVar);
                } catch (Exception | Error $e) {
                    echo $e;
                }
                if ($returnVar === 0)
                    return $outputArray;
                else {
                    $this->log("Error executing process");
                    throw new ExcutionError($process_name);
                }
            } else if ($run_type == static::BACKGROUND) {
                if ($this->execute())
                    return 1;
            }
        } catch (Exception | Error $e) {
            $this->log($e->getMessage() . ", trace: " . trace($e));
            return null;
        }
    }

    public function shutdown()
    {
        $process_name = $this->process_name;
        $pid = $this->pid;
        if ($pid) {
            $this->kill();
            $this->log("Process with name $process_name and PID $pid terminated.\n");
            return 1;
        } else {
            throw new ProcessNotFound($process_name);
        }
    }

    private function kill()
    {
        $process_name = $this->process_name;
        $pid = $this->pid;

        if ($pid && $process_name) {
            if ($this->os == 'WINDOWS') {
                exec("taskkill /F /PID $pid");
            } else if ($this->os == 'LINUX') {
                exec("kill -9 $pid");
            } else {
                $this->log("Please put a valid operating system type in the env file");
                echo ("Please put a valid operating system type in the env file\n");
                return 0;
            }
            $this->log("Process $process_name with PID $pid terminated at .\n");
            echo "Process $process_name with PID $pid terminated.";
            return 1;
        } else {
            throw new ProcessNotFound($process_name);
        }
    }

    private function execute()
    {
        $process_name = $this->process_name;
        $process_path =  APP_DIR . "/processes/$process_name.php";

        if ($_ENV['OS'] == 'WINDOWS') {
            $cmd = "start /B php \"$process_path\" " . implode(" ", $this->args) . " > \"$this->log_path\" 2>&1";

            $descriptorspec = [
                0 => ["pipe", "r"],
                1 => ["pipe", "w"],
            ];

            $prog = proc_open($cmd, $descriptorspec, $pipes);

            if ($prog !== false) {
                $ppid = proc_get_status($prog);
                $pid = $ppid['pid'];
                // var_dump($pid);
                $output = array_filter(explode(" ", shell_exec("wmic process get parentprocessid,processid | find \"$pid\"")));
                // var_dump($output);
                array_pop($output);
                $pid = end($output);

                if (!empty($pid)) {
                    $this->pid = (int) $pid;
                    return 1;
                } else {
                    echo ("Error: PID not found.\n");
                    throw new PIDNotFound($pid);
                }
            } else {
                echo ("Error executing process.\n");
                throw new ExcutionError($process_name);
            }
        } else if ($_ENV['OS'] == 'LINUX') {
            $php_dir = '/usr/local/lsws/lsphp81/bin/php';
            $cmd = "$php_dir " . escapeshellarg($process_path) . ' ' . implode(' ', array_map('escapeshellarg', $this->args)) . ' > ' . escapeshellarg($this->log_path) . ' 2>&1 & echo $!';

            // var_dump($cmd);
            // echo "\n";
            exec($cmd, $output);
            $pid = isset($output[0]) ? trim($output[0]) : null;

            if (!empty($pid) && is_numeric($pid)) {
                $this->pid = $pid;
                echo ("Process {$this->process_name} started in the background with PID: $pid. Logging at {$this->log_path}\n");
                return 1;
            } else {
                echo ("Error: PID not found.\n");
                return 0;
            }
        } else {
            echo ("Please put a valid operating system type in the env file\n");
        }
    }

    public function getState()
    {
        $process_name = $this->process_name;
        $pid = $this->pid;

        if ($pid) {
            if (static::isProcessRunning($pid)) {
                echo ("Process with name $process_name and PID $pid is running.\n");
                return 1;
            } else {
                echo ("Process with name $process_name and PID $pid is not running.\n");
                return 0;
            }
        } else {
            echo ("Process not found.\n");
            throw new ProcessNotFound($process_name);
        }
    }

    private static function isProcessRunning($pid)
    {
        exec("ps -p $pid", $output, $returnVar);
        return $returnVar === 0;
    }
}
