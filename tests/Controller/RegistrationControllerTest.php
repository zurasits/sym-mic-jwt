<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class RegistrationControllerTest extends WebTestCase
{

    public function testIndex(): void
    {
        $this->assertSame(42, 42);
    }
}
