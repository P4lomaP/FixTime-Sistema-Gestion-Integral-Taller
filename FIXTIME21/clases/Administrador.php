<?php
declare(strict_types=1);

final class Administrador
{
    public function __construct(
        public int $id,
        public int $persona_id
    ) {}
}
