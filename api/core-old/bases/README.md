# Endpoint Base Class
The `Endpoint` class is a generic blueprint for all endpoints in your application. It provides a structure and common functionality for handling requests and generating responses. For more about how to use this base class, see app/endpoints/README.md

## Methods

- **`init($expect, $request): void`**:
  - Initializing endpoint.
  - `$expect`: The expected structure of a valid request.
  - `$request`: The request body.
  
  <br>

- **`prehandle(): void`**:
  - Method executed before the main handling process.
  - Validates the request based on the expected structure.
  - Throws a `BadRequest` exception if the validation fails.
  - Filters the request data.
  
  <br>

- **`handle(): Response`**:
  - Abstract method to perform the main handling process.
  - This method should be implemented by subclasses.
  - Should return a `Response` object representing the endpoint's response.
  
  <br>
  
- **`run(): Response`**:
  - Runs the endpoint and returns the response.
  - Executes `prehandle()` before the main handling process.
  - Executes `handle()` to perform the main handling process.
  - Returns the generated `Response` object.
  
  <br>
  
- **`expects($param): bool`**:
  - Checks if a parameter is present in the request.
  - `param`: The name of the parameter to check.
  - Returns `true` if the parameter is present, else `false`.
   
  <br>
  
- **`postHandle(): void`**:
  - Method executed after the main handling process.
  - Can be overridden in subclasses to perform any additional tasks.

> Please note that the `Endpoint` class is an abstract class, and the `handle()` method must be implemented in subclasses.

## Usage
>see app/endpoints/README.md

<br><br>

# Entity Base Class

The `Entity` class serves as a base class for representing entities in the system. It provides functionality for managing entity properties, synchronization with the persistence layer, and accessing the mapper instance associated with the entity.
  
## Methods

- **`copy(Entity $entity): void`**:
  - Copy properties from another entity.
  - Throws an `IncompatibleEntities` exception if the entity classes are incompatible.
  
  <br>

- **`__construct($id)`**:
  - Entity constructor.
  - `$id`: The entity ID.
  
  <br>
  
- **`load(array $data): void`**:
  - Load data into the entity.
  - `$data`: The data to be loaded.
  
  <br>
  
- **`getID(): array`**:
  - Get the entity ID.
  - Returns the entity ID formatted as an associative array.
  
  <br>
  
- **`setID($id): void`**:
  - Set the entity ID.
  - `$id`: The entity ID.
  - Throws a `RequiredPropertyNotFound` exception if a required property is not found in the ID array.
  - Throws an `InvalidID` exception if the ID value is invalid.
  
  <br>
  
- **`naiveGet($property): mixed`**:
  - Naive getter for entity properties.
  - `$property`: The property to retrieve.
  - Returns the property value if found, null otherwise.
  - Throws a `PropertyNotExisting` exception if the property is not found.
  
  <br>
  
- **`get(...$properties): mixed`**:
  - Getter for entity properties.
  - `...$properties`: The properties to retrieve.
  - Returns the property value(s) if found, null otherwise.
  - If the entity is not in sync with the database and a requested property is missing, it will refetch the entity from the database.
  - If only one property is requested, it returns the property value directly.
  
  <br>
  
- **`set(string $property, $value): void`**:
  - Setter for entity properties.
  - `$property`: The property to set.
  - `$value`: The value to set for the property.
  - Throws a `PropertyNotExisting` exception if the property is not found.
  
  <br>
  
- **`getMapper(): Mapper`**:
  - Get the mapper instance for the entity.
  - Returns the mapper instance.
  
  <br>
  
- **`inSync(): bool`**:
  - Check if the entity is in sync with the persistence layer.
  - Returns `true` if the entity is in sync, `false` otherwise.
  
  <br>
  
- **`setSync(bool $state): void`**:
  - Set the sync state of the entity.
  - `$state`: The sync state to set.

> Please note that the `Entity` class is an abstract class, and it provides a basic structure for representing an entity in the system. You should extend this class and implement additional functionality based on your specific needs.

## Usage
> see app/model/entities/README.md

# Mapper Base Class

The `Mapper` class serves as an abstract base class for mapping entities to the underlying data storage, such as a database table. It provides a set of common methods for creating, retrieving, updating, and deleting records associated with a specific entity type.

## Methods

- **`getTableName(): string`**:
  - Returns the name of the database table associated with the entity.

<br>

- **`sanitize(array $data): array`**:
  - Prepares the provided data for insertion into the database.
  - Ensures that required properties are present and optional properties are correctly encoded, such as JSON.

<br>

- **`__construct(Entity $entity)`**:
  - Constructs a new instance of the mapper with the associated entity.

<br>

- **`create(array $entity_data): ?Entity`**:
  - Creates a new record in the database based on the provided entity data.
  - Returns a complete instance of the entity if the creation is successful, otherwise returns `null`.

<br>

- **`save(): bool`**:
  - Updates the existing record in the database if it exists or creates a new one otherwise.
  - Returns a boolean indicating the success of the save operation.

<br>

- **`get(array $filters = []): ?Entity`**:
  - Retrieves a single record from the database based on the provided filters.
  - Returns an instance of the entity if found, otherwise returns `null`.

<br>

- **`getAll(array $filters = []): Entities`**:
  - Retrieves multiple records from the database based on the provided filters.
  - Returns an instance of `Entities` containing the matched records.

<br>

- **`validateFilters(array &$filters)`**:
  - Validates the filters array to ensure that the keys correspond to valid properties of the entity.
  - Modifies the filters array to include additional validation criteria, such as checking for a positive `id` value.

<br>

- **`delete(): bool`**:
  - Deletes the record corresponding to the current entity instance from the database.
  - Returns a boolean indicating the success of the delete operation.

> Please note that the `Mapper` class is an abstract class, and it provides a basic structure for representing an entity in the system. You should extend this class and implement additional functionality based on your specific needs.


## Usage

> see app/model/mappers/README.md