<?php

namespace App\Services\FormulaEngine;

class AstNode
{
    public string $type;
    public $value;
    public ?AstNode $left = null;
    public ?AstNode $right = null;
    public ?AstNode $operand = null;
    public ?string $name = null;
    public array $args = [];

    public static function number(float $value): self
    {
        $node = new self();
        $node->type = 'NUMBER';
        $node->value = $value;
        return $node;
    }

    public static function variable(string $name): self
    {
        $node = new self();
        $node->type = 'VARIABLE';
        $node->value = $name;
        return $node;
    }

    public static function unary(string $operator, AstNode $operand): self
    {
        $node = new self();
        $node->type = 'UNARY';
        $node->value = $operator;
        $node->operand = $operand;
        return $node;
    }

    public static function binary(string $operator, AstNode $left, AstNode $right): self
    {
        $node = new self();
        $node->type = 'BINARY';
        $node->value = $operator;
        $node->left = $left;
        $node->right = $right;
        return $node;
    }

    public static function function(string $name, array $args): self
    {
        $node = new self();
        $node->type = 'FUNCTION';
        $node->name = strtoupper($name);
        $node->args = $args;
        return $node;
    }
}
