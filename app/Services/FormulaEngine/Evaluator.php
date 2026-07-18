<?php

namespace App\Services\FormulaEngine;

class Evaluator
{
    private FunctionRegistry $registry;

    public function __construct(FunctionRegistry $registry)
    {
        $this->registry = $registry;
    }

    public function evaluate(AstNode $node, array $variables, StepTracer $tracer): float
    {
        switch ($node->type) {
            case 'NUMBER':
                return (float) $node->value;

            case 'VARIABLE':
                if (!array_key_exists($node->value, $variables)) {
                    throw new FormulaException('Variable "' . $node->value . '" tidak ditemukan.', 'UNKNOWN_VARIABLE');
                }
                return (float) $variables[$node->value];

            case 'UNARY':
                $operand = $this->evaluate($node->operand, $variables, $tracer);
                if ($node->value === '-') {
                    return -$operand;
                }
                return $operand;

            case 'BINARY':
                $left = $this->evaluate($node->left, $variables, $tracer);
                $right = $this->evaluate($node->right, $variables, $tracer);
                return $this->evaluateBinary($node->value, $left, $right, $tracer);

            case 'FUNCTION':
                $handler = $this->registry->get($node->name);
                $args = [];
                foreach ($node->args as $argNode) {
                    $args[] = $this->evaluate($argNode, $variables, $tracer);
                }

                if (count($args) < $handler->minArgs() || count($args) > $handler->maxArgs()) {
                    throw new FormulaException(
                        'Function ' . $node->name . ' membutuhkan ' . $handler->minArgs() . '-' . $handler->maxArgs() . ' argumen.',
                        'INVALID_ARG_COUNT'
                    );
                }

                return $handler->evaluate($args, $tracer);

            default:
                throw new FormulaException('Formula tidak valid.', 'INVALID_AST');
        }
    }

    private function evaluateBinary(string $operator, float $left, float $right, StepTracer $tracer): float
    {
        switch ($operator) {
            case '+':
                $result = $left + $right;
                $tracer->addStep($this->format($left) . ' + ' . $this->format($right), $result, 'Penjumlahan');
                return $result;

            case '-':
                $result = $left - $right;
                $tracer->addStep($this->format($left) . ' - ' . $this->format($right), $result, 'Pengurangan');
                return $result;

            case '*':
                $result = $left * $right;
                $tracer->addStep($this->format($left) . ' × ' . $this->format($right), $result, 'Perkalian');
                return $result;

            case '/':
                if (abs($right) < 1e-15) {
                    throw new FormulaException('Pembagian dengan nol.', 'DIVISION_BY_ZERO');
                }
                $result = $left / $right;
                $tracer->addStep($this->format($left) . ' ÷ ' . $this->format($right), $result, 'Pembagian');
                return $result;

            case '%':
                if (abs($right) < 1e-15) {
                    throw new FormulaException('Modulo dengan nol.', 'DIVISION_BY_ZERO');
                }
                $result = fmod($left, $right);
                $tracer->addStep($this->format($left) . ' % ' . $this->format($right), $result, 'Modulo');
                return $result;

            case '^':
                $result = pow($left, $right);
                $tracer->addStep($this->format($left) . ' ^ ' . $this->format($right), $result, 'Pangkat');
                return $result;

            default:
                throw new FormulaException('Operator tidak dikenal.', 'UNKNOWN_OPERATOR');
        }
    }

    private function format(float $value): string
    {
        $formatted = rtrim(rtrim(sprintf('%.10F', $value), '0'), '.');
        return $formatted === '' || $formatted === '-0' ? '0' : $formatted;
    }
}
