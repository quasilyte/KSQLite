<?php

namespace KSQLite;

/**
 * KSQLiteArray is a wrapper around simple PHP array.
 * 
 * It's needed to write a KPHP and PHP compatible code that
 * needs to capture array into the lambda by reference.
 * Since KPHP doesn't support captured references, we wrap
 * array into a class instance, that is a reference itself
 * and then capture KSQLiteArray "by value".
 */
class KSQLiteArray {
    /** @var mixed[] */
    public $values = [];
}
