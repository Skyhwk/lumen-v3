<?php

namespace App\Services\FormulaEngine;

class Parser
{
    private array $tokens = [];
    private int $pos = 0;
    private int $depth = 0;
    private const MAX_DEPTH = 20;

    public function parse(array $tokens): AstNode
    {
        $this->tokens = $tokens;
        $this->pos = 0;
        $this->depth = 0;

        if (empty($tokens)) {
            throw new FormulaException('Formula kosong.', 'EMPTY_FORMULA');
        }

        $node = $this->parseExpression();

        if ($this->current() !== null) {
            throw new FormulaException('Formula tidak valid.', 'INVALID_SYNTAX', $this->currentPosition());
        }

        return $node;
    }

    private function parseExpression(): AstNode
    {
        return $this->parseAdditive();
    }

    private function parseAdditive(): AstNode
    {
        $left = $this->parseMultiplicative();

        while ($this->matchOperator(['+', '-'])) {
            $operator = $this->previous()['value'];
            $right = $this->parseMultiplicative();
            $left = AstNode::binary($operator, $left, $right);
        }

        return $left;
    }

    private function parseMultiplicative(): AstNode
    {
        $left = $this->parsePower();

        while ($this->matchOperator(['*', '/', '%'])) {
            $operator = $this->previous()['value'];
            $right = $this->parsePower();
            $left = AstNode::binary($operator, $left, $right);
        }

        return $left;
    }

    private function parsePower(): AstNode
    {
        $left = $this->parseUnary();

        if ($this->matchOperator(['^'])) {
            $operator = $this->previous()['value'];
            $right = $this->parsePower();
            $left = AstNode::binary($operator, $left, $right);
        }

        return $left;
    }

    private function parseUnary(): AstNode
    {
        if ($this->matchOperator(['-'])) {
            $operator = $this->previous()['value'];
            return AstNode::unary($operator, $this->parseUnary());
        }

        if ($this->matchOperator(['+'])) {
            return $this->parseUnary();
        }

        return $this->parsePrimary();
    }

    private function parsePrimary(): AstNode
    {
        if ($this->matchType('NUMBER')) {
            return AstNode::number((float) $this->previous()['value']);
        }

        if ($this->matchType('VARIABLE')) {
            return AstNode::variable($this->previous()['value']);
        }

        if ($this->matchType('FUNCTION')) {
            $name = $this->previous()['value'];
            $this->consumeType('LPAREN', 'Kurung pembuka function tidak ditemukan.');
            $args = $this->parseArgumentList();
            $this->consumeType('RPAREN', 'Kurung belum ditutup.');
            return AstNode::function($name, $args);
        }

        if ($this->matchType('LPAREN')) {
            $this->depth++;
            if ($this->depth > self::MAX_DEPTH) {
                throw new FormulaException('Formula terlalu dalam.', 'MAX_DEPTH');
            }

            $expr = $this->parseExpression();
            $this->consumeType('RPAREN', 'Kurung belum ditutup.');
            $this->depth--;
            return $expr;
        }

        throw new FormulaException('Formula tidak valid.', 'MISSING_OPERAND', $this->currentPosition());
    }

    private function parseArgumentList(): array
    {
        $args = [];

        if ($this->checkType('RPAREN')) {
            return $args;
        }

        do {
            $args[] = $this->parseExpression();
        } while ($this->matchType('COMMA'));

        return $args;
    }

    private function matchOperator(array $operators): bool
    {
        if (!$this->checkType('OPERATOR')) {
            return false;
        }

        if (!in_array($this->current()['value'], $operators, true)) {
            return false;
        }

        $this->pos++;
        return true;
    }

    private function matchType(string $type): bool
    {
        if (!$this->checkType($type)) {
            return false;
        }

        $this->pos++;
        return true;
    }

    private function consumeType(string $type, string $message): void
    {
        if (!$this->matchType($type)) {
            throw new FormulaException($message, 'UNBALANCED_PAREN', $this->currentPosition());
        }
    }

    private function checkType(string $type): bool
    {
        $token = $this->current();
        return $token !== null && ($token['type'] ?? null) === $type;
    }

    private function current(): ?array
    {
        return $this->tokens[$this->pos] ?? null;
    }

    private function previous(): array
    {
        return $this->tokens[$this->pos - 1];
    }

    private function currentPosition(): ?int
    {
        $token = $this->current();
        return $token['position'] ?? null;
    }
}
