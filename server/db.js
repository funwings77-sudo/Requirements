'use strict';
const mysql = require('mysql2/promise');

// .env 기반 커넥션 풀
const pool = mysql.createPool({
  host: process.env.DB_HOST || 'localhost',
  port: Number(process.env.DB_PORT || 3306),
  user: process.env.DB_USER || 'root',
  password: process.env.DB_PASSWORD || '',
  database: process.env.DB_NAME || 'medihim',
  charset: 'utf8mb4',
  waitForConnections: true,
  connectionLimit: 10,
  queueLimit: 0,
  dateStrings: true, // DATE 컬럼을 'YYYY-MM-DD' 문자열로 반환
});

module.exports = pool;
