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

So, the library can be located by `libsqlite3.so.0` name. KSQLite library wants to
find that library by `libsqlite3` name on all platforms (Windows, MacOS, Linux).

To ensure it finds the library under that name, we need to create a symlink.

```bash
$ ln -s /lib/x86_64-linux-gnu/libsqlite3.so.0 /lib/x86_64-linux-gnu/libsqlite3
```

Alternatively, you can use the `$LD_LIBRARY_PATH` on Linux and avoid creating a new symlink inside
the system-wide folders.

```bash
$ ln -s /lib/x86_64-linux-gnu/libsqlite3.so.0 /my/libpath/libsqlite3
export LD_LIBRARY_PATH=$LD_LIBRARY_PATH:/my/libpath/
```
