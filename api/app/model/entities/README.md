> Note that ALL ENTITIES MUST HAVE MAPPERS and ALL MAPPERS MUST HAVE ENTITIES

# How To Create New Entities in The System ?

## Step 1: Extending the `Entity` Class

The `Entity` class provides a generic blueprint for representing entities in a system. It encapsulates common functionality for managing entity properties, handling data synchronization with the persistence layer, and providing basic getter and setter methods.

## Step 2: Add Entity-Specific Properties
Within your extended entity class, you can add properties and methods that are specific to your business requirements.

By adding entity-specific properties, you can define the attributes that are unique to the entity. You can specify their data types and access modifiers based on your needs. Additionally, you can define methods that perform operations specific to the entity, such as calculations, validations, or data transformations.

## Example : 

```php
class User extends Entity
{
  protected string $username;
  protected string $password;
  protected string $email;
  protected string $phone;
  protected ?string $full_name;

  const ADMIN = 1;
  const STUDENT = 2;

  public function getTokenData(): array
  {
    return [
      'id' => $this->get('id')
    ];
  }
}
```

In the above example, we extend the Entity class to create a User entity. We add user-specific properties such as $username, $password, $email, $phone, and $full_name. This allows us to manage user entities in a consistent manner while having the flexibility to customize their properties and behavior.

By extending the Entity class, you can create entity classes tailored to the specific needs of your application, enabling efficient and structured management of different types of entities within your system.

<br>

# What Entity Class Provides ?

## Feature 1: Fetching Entity Attributes
To fetch entity attributes, you can simply do this:

```php
// Assuming $entity is an instance of Entity or its subclass

// Access the value of an attribute
$attributeValue = $entity->get('attribute1', 'attribute2', 'attribute3');
```

### Note 1: Return Type
  > This function receives single or multiple parameters including the values to be retrieved. It returns `an associative array` if multiple parameters are passed. `Otherwise, returns the value only`

## Feature 2: Modifying Entity Attributes
You can modify the attributes of an entity using the set() method:

```php
// Assuming $entity is an instance of Entity or its subclass

// Set a new value for an attribute
$entity->set('attribute_name', 'new_value');
```

### Note 1: Requirement
  > attribute_name must be an `existing property` in the entity

## Feature 3: Checking Entity Attribute Synchronization
You can check whether the attributes of an entity are in sync with the underlying data storage using the inSync() method:

```php
// Assuming $entity is an instance of Entity or its subclass

// Check if the entity attributes are in sync
$inSync = $entity->inSync();

if ($inSync) {
    echo "Entity attributes are in sync.";
} else {
    echo "Entity attributes are not in sync.";
}
```

## Feature 4: Mapper Instance Creation Shortcut
You can easily get a mapper instance for the current entity instance using the getMapper() method

```php
// Assuming $entity is an instance of Entity or its subclass

// Get a mapper for this entity
$mapper = $entity->getMapper();

// Use the mapper to do anything you want with this entity
```

