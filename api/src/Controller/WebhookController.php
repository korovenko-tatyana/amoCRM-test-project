<?php

namespace App\Controller;

use App\Service\WebhookService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

class WebhookController extends AbstractController
{
    public function __construct(protected WebhookService $webhookService)
    {
    }

    #[Route('/', name: 'by_webhook_update', methods: 'POST')]
    public function index(Request $request, MessageBusInterface $bus): Response
    {
        $this->webhookService->setNotes($request, $bus);

        return new Response(status: 200);
    }
}
