<?php

namespace App\PosTui;

class TicketManager
{
    private PosClient $client;
    private ?string $sessionId = null;
    private array $items = [];
    private array $totals = ['subtotal' => 0, 'tax' => 0, 'total' => 0];

    public function __construct(PosClient $client)
    {
        $this->client = $client;
        $this->initializeSession();
    }

    private function initializeSession(): void
    {
        $result = $this->client->createTicket();
        $this->sessionId = $result['session_id'];
        $this->updateFromTicket($result['ticket']);
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getItems(): array
    {
        return $this->items;
    }

    public function getTotals(): array
    {
        return $this->totals;
    }

    public function getTotal(): float
    {
        return $this->totals['total'];
    }

    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    public function addItem(int $productId, int $quantity = 1): void
    {
        $result = $this->client->addItem($this->sessionId, $productId, $quantity);
        $this->updateFromTicket($result['ticket']);
    }

    public function removeLastItem(): void
    {
        if (empty($this->items)) {
            return;
        }

        $lastItem = end($this->items);
        $result = $this->client->removeItem($lastItem['id']);
        $this->updateFromTicket($result['ticket']);
    }

    public function checkout(string $paymentMethod, float $amountPaid): array
    {
        return $this->client->checkout($this->sessionId, $amountPaid, $paymentMethod);
    }

    public function reset(): void
    {
        $this->initializeSession();
    }

    private function updateFromTicket(array $ticket): void
    {
        $this->items = $ticket['items'] ?? [];
        $this->totals = [
            'subtotal' => $ticket['subtotal'] ?? 0,
            'tax' => $ticket['tax'] ?? 0,
            'total' => $ticket['total'] ?? 0,
        ];
    }
}
