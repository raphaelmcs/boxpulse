<?php

namespace App\Tenancy;

use LogicException;

class CurrentBox
{
    private ?int $id = null;

    public function id(): ?int
    {
        return $this->id;
    }

    public function set(int $boxId): void
    {
        if ($this->id !== null && $this->id !== $boxId) {
            throw new LogicException('O tenant não pode mudar durante a operação.');
        }

        $this->id = $boxId;
    }

    public function clear(): void
    {
        $this->id = null;
    }

    public function requireId(): int
    {
        return $this->id ?? throw new LogicException('Contexto da box obrigatório.');
    }
}
