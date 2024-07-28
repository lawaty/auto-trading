<?php


return [
  'unwanted_stocks' => [
    'id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT',
    'symbol VARCHAR(255) NOT NULL',
    'type TEXT',
    'created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
  ],
  'days' => [
    'id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT',
    'name VARCHAR(255) NOT NULL',
    'date VARCHAR(255) NOT NULL',
    'created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
  ],
  'traded_stocks' => [
    'id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT',
    'day_id INT NOT NULL',
    'order_id INT NOT NULL',
    'symbol VARCHAR(255) NOT NULL',
    'pchange FLOAT',
    'price FLOAT',
    'created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
    'FOREIGN KEY(day_id) REFERENCES days(id) ON DELETE CASCADE',
    'FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE CASCADE'
  ],
  'orders' => [
    'id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT',
    'tradestation_order_id INT NOT NULL',
    'day_id INT NOT NULL',
    'symbol VARCHAR(255) NOT NULL',
    'price FLOAT',
    'quantity INT',
    'execution_price FLOAT',
    'execution_type TEXT',
    'execution_details details TEXT',
    'created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
    'FOREIGN KEY(day_id) REFERENCES days(id) ON DELETE CASCADE'
  ]
];
