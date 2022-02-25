# Installation: Debian/Ubuntu

```bash
$ composer require quasilyte/ksqlite
$ sudo apt install sqlite3
```

Now we need to make sure that KPHP/PHP will find the installed library.

```bash
$ ldconfig -p | grep sqlite3
  libsqlite3.so.0 (libc6,x86-64) => /lib/x86_64-linux-gnu/libsqlite3.so.0
```

We're interested in `libsqlite3.so.0` entry. KSQLite library wants to
find that library by `./ffilibs/libsqlite3` name on all platforms (Windows, MacOS, Linux).

To ensure it finds the library under that name, we need to create a symlink.

```bash
$ mkdir -p ffilibs
$ ln -s /lib/x86_64-linux-gnu/libsqlite3.so.0 /lib/x86_64-linux-gnu/libsqlite3
```
