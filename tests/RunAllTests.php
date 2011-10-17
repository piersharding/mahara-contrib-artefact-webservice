<?php
require_once 'PHPUnit/Framework.php';

class RunAllTests {

    public static function suite() {
        $suite = new PHPUnit_Framework_TestSuite('Mahara Web Services TestSuite');
        $tests = preg_grep('/RunAllTests|TestBase/', glob(dirname(__FILE__) . '/*.php'), PREG_GREP_INVERT);
        foreach ($tests as $test) {
            error_log('adding test: ' . $test);
            $test = basename($test);
            $parts = explode('.', $test);
            echo "Setting up: $parts[0]\n";
            require_once($test);
            $suite->addTestSuite($parts[0]);

        }
        return $suite;
    }
}
