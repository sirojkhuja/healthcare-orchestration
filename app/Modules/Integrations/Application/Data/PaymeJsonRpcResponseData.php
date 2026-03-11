<?php

namespace App\Modules\Integrations\Application\Data;

final readonly class PaymeJsonRpcResponseData
{
    /**
     * @param  array<string, mixed>|null  $result
     * @param  array<string, mixed>|null  $error
     */
    private function __construct(
        public mixed $id,
        public ?array $result,
        public ?array $error,
    ) {}

    /**
     * @param  array<string, mixed>  $result
     */
    public static function result(mixed $id, array $result): self
    {
        return new self($id, $result, null);
    }

    public static function error(mixed $id, int $code, string $message, mixed $data = null): self
    {
        if ($data !== null) {
            /** @var array<string, mixed> $error */
            $error = [
                'code' => $code,
                'message' => $message,
                'data' => $data,
            ];

            return new self($id, null, $error);
        }

        /** @var array<string, mixed> $error */
        $error = [
            'code' => $code,
            'message' => $message,
        ];

        return new self($id, null, $error);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = [
            'jsonrpc' => '2.0',
            'id' => $this->id,
        ];

        if ($this->error !== null) {
            $payload['error'] = $this->error;

            return $payload;
        }

        $payload['result'] = $this->result ?? [];

        return $payload;
    }
}
