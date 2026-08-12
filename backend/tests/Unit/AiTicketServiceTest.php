<?php

namespace Tests\Unit;

use App\Services\AiTicketService;
use Tests\TestCase;

class AiTicketServiceTest extends TestCase
{
    public function test_ai_ticket_service_can_be_resolved(): void
    {
        $service = $this->app->make(AiTicketService::class);

        $this->assertInstanceOf(AiTicketService::class, $service);
    }
}