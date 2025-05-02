<?php

const API_BASE = "api";

// DB Mode
const MYSQL = 1;
const SQLITE = 2;
const DB_MODE = SQLITE; // or SQLITE

// DB VARS
/**
 * Should be defined in .env:
 * (MySQL)
 * 1. DB_HOST
 * 2. DB_NAME
 * 3. DB_USER
 * 4. DB_PASS
 * 
 * (Sqlite)
 * 1. DB_PATH
 */

// Default Timezone
const TIMEZONE = 'America/New_York'; // Very important for US market timing


// Locations
const APP_DIR = __DIR__ . '/app';
const CORE_DIR = __DIR__ . '/core';
const ROOT_DIR = __DIR__;

const ENDPOINTS_DIR =  APP_DIR . "/endpoints";
const ROUTES_JSON = ROOT_DIR . "/routes.json";

const PLUGINS_DIR = ROOT_DIR . "/plugins";
const LOG_DIR =  ROOT_DIR . "/logs";
const MEDIA_DIR =  ROOT_DIR . "/media";

const MODEL_DIR = APP_DIR . '/model';
const ENTITIES_DIR = MODEL_DIR . '/entities';
const MAPPERS_DIR = MODEL_DIR . '/mappers';
const SERVICES_DIR = MODEL_DIR . '/services';

const SYSTEM_HELPERS_DIR = CORE_DIR . '/helpers';
const USER_HELPERS_DIR = APP_DIR . '/helpers';

const SYSTEM_SERVICES_DIR = CORE_DIR . '/services';

const SYSTEM_BASES_DIR = CORE_DIR . '/bases';
const USER_BASES_DIR = APP_DIR . '/bases';

const SYSTEM_EXCEPTIONS_DIR = CORE_DIR . '/exceptions';
const USER_EXCEPTIONS_DIR = APP_DIR . '/exceptions';

const USER_LIBS_DIR = APP_DIR . '/lib';
const TMP_DIR = ROOT_DIR . '/tmp';

const DB_CORE = CORE_DIR . '/database';
const JSONS_DIR = APP_DIR . '/model/jsons';