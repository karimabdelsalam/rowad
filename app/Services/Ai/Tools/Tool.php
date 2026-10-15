<?php

namespace App\Services\Ai\Tools;

use App\Models\User;
use Closure;

/**
 * One question the assistant can ask the office's data. Tools are the only way the model sees data:
 * each one runs a fixed, scoped query as the signed-in user (no free SQL), so permissions and
 * portal boundaries hold even if a prompt tries to talk the model into more.
 */
final class Tool
{
    /**
     * @param  array<string, array<string, mixed>>  $properties  JSON-schema properties of the input
     * @param  Closure(array, User): array  $handler
     */
    private function __construct(
        public readonly string $name,
        public readonly string $description,
        private readonly array $properties,
        private readonly ?string $permission,
        private readonly Closure $handler,
    ) {}

    public static function make(string $name, string $description, array $properties, ?string $permission, Closure $handler): self
    {
        return new self($name, $description, $properties, $permission, $handler);
    }

    public function allows(User $user): bool
    {
        return $this->permission === null || $user->is_super_admin || $user->can($this->permission);
    }

    /** @return array<string, mixed> the tool as the Messages API expects it */
    public function definition(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'input_schema' => ['type' => 'object', 'properties' => $this->properties ?: new \stdClass],
        ];
    }

    /** @return array<string, mixed> */
    public function run(array $input, User $user): array
    {
        if (! $this->allows($user)) {
            return ['error' => 'not_permitted'];
        }

        return ($this->handler)($input, $user);
    }
}
