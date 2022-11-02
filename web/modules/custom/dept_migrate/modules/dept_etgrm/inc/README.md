# Naming conventions

When adding a new MySQL Stored Procedure or Function you must follow the naming convention outlined below for the module
to automatically add and remove it from the database when installing and uninstalling.

Paste your procedure or function into a file with the prefix of sql_ and a file extension of 'sproc'
the main filename must match your function name, in lower snake case.

Example:

```sql
CREATE PROCEDURE FETCH_ALL_USERS()
BEGIN
  SELECT * FROM users
END
```

This sproc should have the filename: sql_fetch_all_users.sproc


