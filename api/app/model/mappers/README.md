> Note that ALL ENTITIES MUST HAVE MAPPERS and ALL MAPPERS MUST HAVE ENTITIES

> Make sure to read app/model/entities/README.md

<br>

# How To Create New Mappers in The System ?

## Step 1: Extend the Mapper Class

The Mapper class provides a foundation for mapping entities to the underlying data storage, such as a database table. By extending the Mapper class, you can create specialized mapper classes for specific entity types within your system.

## Step 2: Define Entity-Specific Properties
Within your specialized mapper class, define the properties specific to the entity type you are creating. These properties represent the fields or attributes that characterize the entity. You can specify whether they are required or optional for the entity.

## Step 3: Customize the Mapper Class
Customize the behavior of the mapper class to fit the requirements of your entity. You can override or extend the inherited methods from the Mapper class to implement entity-specific logic or perform additional validation.

## Example : Define a new mapper type in the system

```php
class UserMapper extends Mapper
{
  protected static string $table = 'users';
  protected static array $required = ['username', 'password', 'email'];
  protected static array $optional = ['phone', 'full_name']; // Just delete this line if no optional fields exist

  // Implement additional methods specific to UserMapper...
}
```

In the above example, we extend the Mapper class to create a UserMapper class. We define the entity-specific properties such as $username, $password, $email, $phone, and $full_name, and specify their required or optional status. This allows us to manage user entities in a structured manner and ensures the necessary properties are present when creating or updating user records.

By extending the Mapper class and customizing it for your entity types, you can create a modular and efficient system for managing different entities within your application.

<br>

# What Mapper Class Provides ?

## Feature 1: Creating a new User
  
To create a new entity of type User in the system, you can simply do this:

```php
// Step 2: Prepare User Data
$userData = [
  'username' => 'john_doe',
  'password' => 'secretpassword',
  'email' => 'john.doe@example.com',
  'phone' => '123-456-7890',
  'full_name' => 'John Doe'
];

// Step 3: Create the User
$newUser = UserMapper::create($userData);

// Step 4: Check the Result
if ($newUser instanceof User) {
  // User created successfully
  // Proceed with further operations or access the created user's data
} else {
  // User creation failed
  // Handle the failure scenario
}
```

### Note1: Preparing user data
  > You can adjust the optional properties based on your specific requirements. If any optional properties are not provided, they will be omitted when creating the user but `make sure to include all the required information` otherwise the creation fails i.e. returns null.

### Note2: create() function
  > The `create()` method will validate the provided data, sanitize it for insertion, and perform the necessary database operations to create a new user record. If the creation is successful, the method will `return a complete entity instance` representing the newly created entity. Otherwise, it will return null.

<br>

## Feature 2: Fetching a Single Entity
In order to fetch a single entity from the database, you can simply do this:

```php
// Step 1: Prepare the filters
$filters = [
  'username' => 'john_doe'
];

// Step 2: Retrieve the User
$user = UserMapper::get($filters);

// Step 3: Check the Result
if ($user instanceof User) {
  // User found
  // Access the user's data or perform further operations
} else {
  // User not found
  // Handle the scenario when the user is not found in the database
}
```

### Note1: Preparing Filters
  > Filters are completely optional and they are key-value pairs such that `keys must be found in entity attributes`

### Note 2: get() function
  > This function retrieves the user from the database based on the specified filters by performing the necessary database operations then `returns a complete entity instance` representing the fetched entity. Otherwise, it will return null

### Note 3: Special Case
  > If multiple records matched the applied filters, the method will only use the first record and neglect all the others.

<br>

## Feature 3: Fetching Multiple Entities
In order to fetch multiple entities from the database, you can simply do this:

```php
// Fetching multiple instances of users
$filters = [
  'status' => 'active',
  'role' => 'admin'
];

$users = UserMapper::getAll($filters);

foreach ($users as $user) {
  // Access individual user properties
  $username = $user->get('username');
  $email = $user->get('email');

  // Perform operations on each user
  // ...
}
```

### Note 1: getAll() function
  > The returned $users variable represents an `instance of Entities`, which allows you to iterate over the collection of users using a foreach loop such that every item in the loop is `an entity instance`.

<br>

## Feature 4: Deleting an Entity
In order to delete an entity from the database, you can simply do this:

```php
// Assuming $user is an instance of User entity

// Delete the user entity
$success = $user->getMapper()->delete();

// Check if the entity was successfully deleted
if ($success) {
    echo "User deleted successfully!";
} else {
    echo "Failed to delete the user.";
}
```

### Note 1: delete() function
  > delete function returns a simple success bool flag

<br>

## Feature 5: Saving New Entity Attributes
In order to save entity changes to the database, you can simply do this:

```php
// Assuming $user is an instance of User entity

// Update the user attributes
$user->set('username', 'new_username');
$user->set('email', 'new_email');

// Save the changes
$success = $user->getMapper()->save();

// Check if the changes were successfully saved
if ($success) {
    echo "User attributes saved successfully!";
} else {
    echo "Failed to save the user attributes.";
}
```

### Note 1: save() function
  > save function returns a simple success bool flag

### Note 2: Success Condition
  > you can use `$user->inSync()` instead of $success condition to do the same