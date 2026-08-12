<?php

use App\Services\TicketDuplicateDetector;
use PHPUnit\Framework\TestCase;

class TicketDuplicateDetectorTest extends TestCase
{
    public function test_similarity_high_for_same_text()
    {
        $detector = new TicketDuplicateDetector();
        $ref = new ReflectionClass($detector);
        $method = $ref->getMethod('similarity');
        $method->setAccessible(true);

        $score = $method->invokeArgs($detector, ['Password reset not working', 'Password reset not working']);
        $this->assertGreaterThanOrEqual(0.99, $score);
    }

    public function test_similarity_low_for_different_text()
    {
        $detector = new TicketDuplicateDetector();
        $ref = new ReflectionClass($detector);
        $method = $ref->getMethod('similarity');
        $method->setAccessible(true);

        $score = $method->invokeArgs($detector, ['Printer not printing', 'Cannot login to email']);
        $this->assertLessThan(0.5, $score);
    }
}
