### Architecture Specifications
<br>

## Framework Features
This framework is designed to be an API framework that provides essential features for hosting APIs. It includes base classes for defining endpoints, entity classes, data mappers, proxy classes, logging classes, and more. Currently, the API framework only supports SQLite database integration, but it is designed to facilitate future migration to MySQL.

Let's start by examining the file hierarchy.

<br>

# FHS
At the root directory, we can find several files and two main folders:

## 1- `core` directory
The `core` directory contains the framework engine, which includes all the base classes and necessary components for running the framework.

- Default base classes are defined inside the `core/bases` directory.
- DB management scripts and the database wrapper class are located in the `core/database` directory.
- Default exceptions are defined in the `core/exceptions` directory.
- Third-party libraries used by the framework can be found in the `core/lib` directory.
- The Logger class logs all requests made to the server and stores them in the `core/logs` folder.
- The `core/proxy` directory contains classes that serve as a proxy layer.
- `core/git.php` is a git auto-deployment script (currently unfinished).
- `core/shell.php` is a third-party script that provides an HTML/CSS/JS/PHP terminal.

## 2- `app` directory
The `app` directory contains the scripts specific to the current application.

- Additional base classes for the application can be implemented in the `app/bases` directory.
- API endpoints are defined in the `app/endpoints` directory.
- Helper functions can be found in the `app/helpers` directory.
- The `app/model` directory is where entities and their data mappers are defined.
- The `app/database.php` file specifies the database schema in SQLite format.

## autoload.php
The `autoload.php` script allows developers to define classes in new files without the need to manually require or include those files.

## config.php
The `config.php` file defines macros and global variables that can be accessed throughout the framework. It also contains the setup arguments for the framework.

## functions.php
The `functions.php` file includes some global functions, although it is recommended to use them sparingly.

<br>
<br>

# How the Program is Executed

The program execution starts in the `index.php` file. This file works in conjunction with instances of Logger, Router, and Endpoint to deliver the response.

The `$response->echo()` function is responsible for closing the connection with the client and flushing the output buffer. This ensures that the `postHandle` method is fully executed after the response is sent back to the client.

The `Router::route()` method searches for the requested endpoint by the client and returns an instance of the `Endpoint` class if found. This allows us to call `$endpoint->run()` and `$endpoint->postHandle()` for that specific endpoint.


## How Router Works?

The Router component plays a crucial role in the framework by parsing the incoming requests and locating the corresponding endpoint. Here's an overview of how the Router works:

1. The Router receives the incoming request from the client.
2. It analyzes the request and extracts relevant information such as the HTTP method and the requested endpoint URL.
3. The Router then searches for the requested endpoint within the `app/endpoints` directory.
4. Endpoints in the framework are organized into controllers. Controller directories serve as groupings for endpoints.


<br>

# What an endpoint represents in this architecture ?
See core/bases/README.md

# How to define endpoints ?
See app/endpoints/README.md for more info about the endpoints 

# How to define new abstract base classes ?
See app/bases/README.md

# How to define my business logic classes ?
See app/model/entities/README.md

See app/model/mappers/README.md

# TODO
## Nframework v3.0.0 (Testable APIs)
> 1. `Serve multiple versions` at the same time safely  // INVALID
> 2. `Safely update framework core` anytime without affecting the app
> 3. `MySQL Support`
> 4. `Dynamic Routes` using JSON files with much flexible and pretty routes using `dynamic parameters`
> 5. `Wordpress Support` using MySQL integration, we can easily integrate wordpress dashboard inside our project
> 6. `Mapper Direct Foreign Linking` meaning to have the ability to get a foreign object from its link directly using basic entity getter instead of fetching its foreign_id
> 7. `Plugins Store` that can easily define its own routes and services easily

1. Interface-based model classes // INVALID 
2. Update proxy files // Done
3. Update global files // Done
4. Automated Tests // Done
5. Define vs Const // Done
6. Better Separation for core files // Done
7. MySQL Wrapper // Done
8. MySQL Database automation scripts // Done
10. Entity direct foreign linking // Done
11. Dynamic Endpoints // INVALID — made dynamic routes instead
14. JsonSerializable Entities // Done
15. save/delete are callable directly from entity object // Done
16. used normal set method instead of edit with save for updating persistance layer // Done
17. Smarter DBScript and DB works for both MySQL and SQLite // Done
18. Better Separation allows overriding core classes in app thus updating core files without affecting the application behavior // Done
20. Plugins Complete Structure (src files, autoload, install, uninstall)
21. Plugins Arte Core API (3 endpoints for managing plugins — install, uninstall, reinstall)
22. ResponseFile for reading files and flushing them easily
23. Flexible parameterized Response headers

9. Mapper naive joining // Won't work on this now
12. Plugins // Not Yet
13. Wordpress // Not Yet
19. Update Framework Documentation :(
20. Remove libraries (aws and phpmailer and build plugin store)