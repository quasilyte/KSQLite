# KSQLite3

KSQLite3 is a FFI-based SQLite library that can be used in both PHP and KPHP.

It's API is close to the PHP-native [SQLite3](https://www.php.net/manual/en/book.sqlite3.php),
but it does focus on the proper error handling more: errors are signalled via return values,
no error printing under the hood ever happens. It's also exception-free.

## Examples

* [examples/quick_start] shows the basic library usage and explains some fundamental concepts
