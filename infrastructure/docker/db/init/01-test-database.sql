-- The test suite runs on galette_test (doctrine dbname_suffix), which the app user must be able to create
GRANT ALL PRIVILEGES ON `galette\_test%`.* TO 'galette'@'%';
