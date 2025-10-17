<?php
declare(strict_types=1);

final class Empleado
{
    public function __construct(
        public int $id,
        public int $persona_id,
        public int $cargo_id
    ) {}
}
