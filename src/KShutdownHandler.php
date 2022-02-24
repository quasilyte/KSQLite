<?php

namespace KSQLite;

class KShutdownHandler {
  private static bool $registered = false;

  /** @var KSQLite[] */
  private static $databases = [];

  public static function pushDatabase(KSQLite $db) {
    if (!self::$registered) {
      self::register();
    }
    self::$databases[] = $db;
  }

  private static function register() {
    self::$registered = true;
    register_shutdown_function(function () {
      foreach (self::$databases as $db) {
        // Even if $db is closed already, it's OK to do
        // it again ($db will remember its state).
        $db->close();
      }
    });
  }
}
