<?php
declare(strict_types=1);
namespace App\Helpers;

class Validator {
    private array $errors = [];
    public function required(string $f, $v): self {
        if ($v === null || trim((string)$v) === '') $this->errors[$f] = "Campo '$f' é obrigatório.";
        return $this;
    }
    public function minLen(string $f, string $v, int $min): self {
        if (strlen($v) < $min) $this->errors[$f] = "'$f' precisa ter ao menos $min caracteres.";
        return $this;
    }
    public function email(string $f, string $v): self {
        if ($v && !filter_var($v, FILTER_VALIDATE_EMAIL)) $this->errors[$f] = "E-mail inválido.";
        return $this;
    }
    public function numeric(string $f, $v): self {
        if (!is_numeric($v)) $this->errors[$f] = "'$f' deve ser numérico.";
        return $this;
    }
    public function fails(): bool { return !empty($this->errors); }
    public function errors(): array { return $this->errors; }
    public static function sanitize($v): string {
        return htmlspecialchars(strip_tags(trim((string)$v)), ENT_QUOTES, 'UTF-8');
    }
    public static function body(): array {
        $raw = file_get_contents('php://input');
        return json_decode($raw ?: '{}', true) ?? [];
    }
}
