<?php
class ArteCurl
{
    public function __construct()
    {   
    }
    public static function request($request_type, $url, $data = [], $headers = [])
    {
        // Initialize cURL session
        $curl = curl_init();

        if (strtolower($request_type) == 'post') {
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_POST, true);

            if (is_array($data)) {
                $post_fields = json_encode($data); // JSON
                if (!isset($headers['Content-Type'])) {
                    $headers['Content-Type'] = 'application/json'; // Default to JSON if Content-Type is not set
                }
            } else {
                $post_fields = $data; // Assuming $data is already a JSON string
                if (!isset($headers['Content-Type'])) {
                    $headers['Content-Type'] = 'application/json'; // Default to JSON if Content-Type is not set
                }
            }

            curl_setopt($curl, CURLOPT_POSTFIELDS, $post_fields);

            // Debug output for POST request
            // var_dump("Request Type: POST\n");
            // var_dump("Request URL: $url\n");
            // var_dump("Payload: ", $post_fields);
        } else if ($request_type == 'GET') {
            $full_url = $url;
            if (!empty($data)) {
                $full_url .= '?' . http_build_query($data);
            }
            curl_setopt($curl, CURLOPT_URL, $full_url);

            // Debug output for GET request
            // var_dump("Request Type: GET\n");
            // var_dump("Request URL: $full_url\n");
            // var_dump("Data: ", $data);
        } else if ($request_type == 'DELETE') {
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "DELETE");

            if (!empty($data)) {
                if (is_array($data)) {
                    $delete_fields = json_encode($data); // JSON
                    if (!isset($headers['Content-Type'])) {
                        $headers['Content-Type'] = 'application/json'; // Default to JSON if Content-Type is not set
                    }
                } else {
                    $delete_fields = $data; // Assuming $data is already a JSON string
                    if (!isset($headers['Content-Type'])) {
                        $headers['Content-Type'] = 'application/json'; // Default to JSON if Content-Type is not set
                    }
                }

                curl_setopt($curl, CURLOPT_POSTFIELDS, $delete_fields);

                // Debug output for DELETE request
                // var_dump("Request Type: DELETE\n");
                // var_dump("Request URL: $url\n");
                // var_dump("Payload: ", $delete_fields);
            }
        } else if ($request_type == 'PUT') {
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");

            if (is_array($data)) {
                $put_fields = json_encode($data); // JSON
                if (!isset($headers['Content-Type'])) {
                    $headers['Content-Type'] = 'application/json'; // Default to JSON if Content-Type is not set
                }
            } else {
                $put_fields = $data; // Assuming $data is already a JSON string
                if (!isset($headers['Content-Type'])) {
                    $headers['Content-Type'] = 'application/json'; // Default to JSON if Content-Type is not set
                }
            }

            curl_setopt($curl, CURLOPT_POSTFIELDS, $put_fields);

            // Debug output for PUT request
            // var_dump("Request Type: PUT\n");
            // var_dump("Request URL: $url\n");
            // var_dump("Payload: ", $put_fields);
        }

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        if (!empty($headers)) {
            $formatted_headers = [];
            foreach ($headers as $key => $value) {
                $formatted_headers[] = "$key: $value";
            }
            curl_setopt($curl, CURLOPT_HTTPHEADER, $formatted_headers);

            // Debug output for headers
            // var_dump("Headers: ", $formatted_headers);
        }

        $response = curl_exec($curl);

        // Get the status code
        $http_status_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if (curl_errno($curl)) {
            var_dump('cURL error: ' . curl_error($curl));
        }

        curl_close($curl);

        $json_response = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            // var_dump("JSON Response: ", $json_response);
            return $json_response;
        } else {
            // var_dump("Raw Response: ", $response);
            return $response;
        }
    }



    public static function stream($url, $headers = [], $callback)
    {
        // Initialize cURL session
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_BUFFERSIZE, 128); // Smaller buffer size for more frequent callbacks

        $continue = true; // Variable to control the loop

        curl_setopt($curl, CURLOPT_WRITEFUNCTION, function ($ch, $data) use ($callback, &$continue) {
            $lines = explode("\n", $data);
            foreach ($lines as $line) {
                $line = trim($line);
                if (!empty($line)) {
                    // Call the callback function
                    $result = call_user_func($callback, $line);
                    if ($result === false) { // Check if callback signals to stop
                        $continue = false;
                        break;
                    }
                }
            }
            return strlen($data);
        });

        if (!empty($headers)) {
            $formatted_headers = [];
            foreach ($headers as $key => $value) {
                $formatted_headers[] = "$key: $value";
            }
            curl_setopt($curl, CURLOPT_HTTPHEADER, $formatted_headers);
        }

        while ($continue) {
            curl_exec($curl);

            if (curl_errno($curl)) {
                var_dump('cURL error: ' . curl_error($curl));
                break;
            }

            // Optionally sleep for a short interval to avoid busy-waiting
            usleep(100000); // 100 milliseconds
        }
        self::endStream($curl);
        return;
    }

    public static function endStream(&$curl)
    {
        if ($curl) {
            curl_close($curl);
            $curl = null;
        }
    }


    private static function parseToURL($data)
    {
        return http_build_query($data);
    }
}
