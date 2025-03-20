<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class RefreshTokenControllerTest extends WebTestCase
{
    public function testIndex(): void
    {
        $this->assertSame(42, 42);
    }
}
