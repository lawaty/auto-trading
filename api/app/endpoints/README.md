> Make sure to see app/bases/README.md first

# How to define a new endpoint ?
Each endpoint is implemented as a `concrete class` that extends one of the base classes provided by the framework or defined inside `app/bases` directory.

To define an endpoint, you need to create a `new class that extends the appropriate base class and implement the necessary methods`. The endpoint class should define its `request validation criteria`, `handle method`, and `postHandle method`.

<br>
<br>

## Validation Criteria

The request validation criteria are specified using the `init function` inside the endpoint's constructor. It takes two arguments: an array that forms the `expected request structure`, and an array containing the `request parameters`.

Here's an example of how the init function is used to define the validation criteria:

```php
$this->init([
  'group_id' => [true, Regex::ID],
  'title' => [false, Regex::NAME],
  'grade' => [false, Regex::GRADE],
  'fees' => [false, Regex::INT]
], $_POST);
```

In this example, the validation criteria are defined for four request parameters: group_id, title, grade, and fees. Each parameter is specified as a key-value pair in the validation criteria array. The key represents the parameter name, and the value is an array that consists of two elements.

The first element of the array indicates whether the parameter is required (true) or optional (false). The second element specifies the regular expression pattern (Regex::ID, Regex::NAME, Regex::GRADE, Regex::INT) that the parameter should match.

By defining the validation criteria for an endpoint, you ensure that the incoming requests adhere to the expected structure and meet the specified criteria before the endpoint's handle method is executed.

<br>
<br>

### preHandle method
This method is already implemented in base classes but you can override it to customize the endpoint flow.
```php
/**
 * Prehandle method
 *
 * Handles pre-processing tasks before the main handle method is executed.
 */
protected function prehandle(): void
{
  // Perform pre-processing tasks

  parent::prehandle(); // Call parent method to validate the request
}
```

<br>
<br>

### handle method

The `handle` method is a public function that processes the client's request and must return a `Response` instance or throw one of the defined exceptions. handle method is abstract in all base classes so `it must be implemented in all endpoints`

```php
  /**
   * handle method
   *
   * @return Response The response to be sent back to the client.
   * @throws Exception If an error or exceptional condition occurs during request processing.
   */
  public function handle(): Response
  {
    // Process the request and perform necessary actions

    // $this->request holds the sanitized version of the request parameters
    doAnyThingWith($this->request);

    // Generate the response
    $responseBody = "Hello, World!";
    $responseCode = 200;
    $response = new Response($responseBody, $responseCode);
    return $response;
  }
```

The handle method performs the actual processing of the request and generates the response. It can contain various operations and logic based on the specific requirements of the endpoint. The generated response is encapsulated within a Response instance, which includes the response body and response code. The response body can be a string or any JSON-serializable object. The response code indicates the status of the response, such as 200 for success, 404 for not found, etc. If an error or exceptional condition occurs during the request processing, a defined exception can be thrown to handle the specific situation and return an appropriate response to the client.

<br>
<br>

### postHandle method

The `postHandle` method is a public void function that can be defined optionally to perform additional actions after the response is returned. It is called after the `handle` method and can be used to execute any necessary processes to improve the responsiveness of the API.

```php
/**
 * postHandle method
 *
 * Performs additional actions after the response is returned.
 */
public function postHandle(): void
{
    // Perform additional actions or processes

    // No return value
}
```

The postHandle method allows you to execute any post-processing tasks or actions that need to be performed after the response has been returned to the client. This can include tasks such as logging, cleanup operations, or triggering any additional processes that enhance the API's responsiveness. The method does not have a return value, as its purpose is to carry out actions rather than generating a response.

<br>
<br>

## Complete Endpoint Example Code

```php
/**
 * Example Endpoint
 *
 * This is an example endpoint that demonstrates the structure and functionality of an endpoint class.
 * Replace this class with your own custom endpoint implementations.
 */
class ExampleEndpoint extends Endpoint
{
    /**
     * Initializing endpoint
     *
     * @param expect: expected structure of a healthy request
     * @param request: the request body
     */
    public function __construct($expect, $request)
    {
      $expect = [
        'param1' => [REQUIRED_OR_OPTIONAL, Regex::ANYTHING],
        'param2' => [REQUIRED_OR_OPTIONAL, Regex::ANYTHING],
        'param3' => [REQUIRED_OR_OPTIONAL, Regex::ANYTHING]
      ];
      $request = array_merge($_POST, $_FILES); // Customize it to match your needs
      $this->init($expect, $request);
    }

    /**
     * Prehandle method
     *
     * Handles pre-processing tasks before the main handle method is executed.
     */
    protected function prehandle(): void
    {
      // Perform pre-processing tasks, such as request validation

      parent::prehandle(); // Call parent method to validate the request
    }

    /**
     * Handle method
     *
     * Process the client's request and return a Response instance or throw an exception.
     *
     * @return Response
     * @throws BadRequestException
     */
    protected function handle(): Response
    {
      // Process the client's request and generate a response

      // Example: Create a response with a success message
      $responseBody = "Request processed successfully";
      $responseCode = 200;

      return new Response($responseBody, $responseCode);
    }

    /**
     * PostHandle method
     *
     * Performs additional actions after the response is returned.
     */
    public function postHandle(): void
    {
      // Perform additional actions or processes after the response is returned
    }
}
```