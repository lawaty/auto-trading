# How To implement a new base class for endpoints ?

To implement a new endpoint base class that adds an additional processing layer, you can create a new class that extends the Endpoint class and incorporates the desired functionality. Here's an example of how you can implement a new endpoint base class, AdditionalProcessing, based on the provided Authenticated abstract base class:

```php
abstract class AdditionalProcessing extends Endpoint
{
  protected function prehandle(): void
  {
    // Perform additional processing before the endpoint regular prehandling
    $this->customPrehandle1();

    // Call the parent prehandle method to perform the regular processing
    parent::prehandle();

    // Perform additional processing just before the endpoint handle method is called
    $this->customPrehandle2();
  }

  protected function customPrehandle1(): void
  {
    // Add your custom processing logic here
    // This method will be called before the regular prehandle method in the parent class
    // Perform any additional steps or checks
  }

    protected function customPrehandle2(): void
  {
    // Add your custom processing logic here
    // This method will be called right after the regular prehandle method in the parent class and before the handle method
    // Perform any additional steps or checks
  }
}
```

In this example, the AdditionalProcessing class extends the Endpoint class and overrides the prehandle() method to include an additional processing layer. The customPrehandle() method is introduced to allow you to define your custom logic before the regular prehandle method is called.