<?php

namespace App\Services;

use App\Services\FormulaEngine\Evaluator;
use App\Services\FormulaEngine\FormulaException;
use App\Services\FormulaEngine\FunctionRegistry;
use App\Services\FormulaEngine\Functions\AbsFunction;
use App\Services\FormulaEngine\Functions\CeilFunction;
use App\Services\FormulaEngine\Functions\FloorFunction;
use App\Services\FormulaEngine\Functions\LogFunction;
use App\Services\FormulaEngine\Functions\MaxFunction;
use App\Services\FormulaEngine\Functions\MinFunction;
use App\Services\FormulaEngine\Functions\PowerFunction;
use App\Services\FormulaEngine\Functions\RoundFunction;
use App\Services\FormulaEngine\Functions\TruncFunction;
use App\Services\FormulaEngine\Functions\SqrtFunction;
use App\Services\FormulaEngine\Lexer;
use App\Services\FormulaEngine\Parser;
use App\Services\FormulaEngine\StepTracer;
use App\Services\FormulaEngine\Validator;

class FormulaService
{
    private Lexer $lexer;
    private Parser $parser;
    private Validator $validator;
    private Evaluator $evaluator;
    private FunctionRegistry $registry;

    public function __construct()
    {
        $this->registry = new FunctionRegistry();
        $this->registerDefaultFunctions();

        $this->lexer = new Lexer();
        $this->parser = new Parser();
        $this->validator = new Validator($this->registry);
        $this->evaluator = new Evaluator($this->registry);
    }

    public function getAllowedFunctions(): array
    {
        return $this->registry->allowedNames();
    }

    public function validate(string $formula, array $allowedVariables): array
    {
        try {
            $tokens = $this->lexer->tokenize($formula);
            $validation = $this->validator->validate($tokens, $allowedVariables);

            if (!$validation['valid']) {
                return $validation;
            }

            $ast = $this->parser->parse($tokens);
            $this->validator->validateAst($ast, $this->registry);

            return ['valid' => true, 'errors' => [], 'message' => 'Formula valid'];
        } catch (FormulaException $e) {
            return [
                'valid' => false,
                'errors' => [[
                    'code' => $e->errorCode,
                    'message' => $e->getMessage(),
                    'position' => $e->position,
                ]],
            ];
        }
    }

    public function calculate(string $formula, array $variables): array
    {
        $allowed = array_keys($variables);
        $validation = $this->validate($formula, $allowed);

        if (!$validation['valid']) {
            return $validation;
        }

        try {
            $tokens = $this->lexer->tokenize($formula);
            $ast = $this->parser->parse($tokens);
            $tracer = new StepTracer();
            $result = $this->evaluator->evaluate($ast, $variables, $tracer);

            return [
                'valid' => true,
                'result' => $result,
                'result_formatted' => $this->formatResult($result),
                'steps' => $tracer->getSteps(),
                'formula_substituted' => $this->substituteVariables($formula, $variables),
            ];
        } catch (FormulaException $e) {
            return [
                'valid' => false,
                'errors' => [[
                    'code' => $e->errorCode,
                    'message' => $e->getMessage(),
                    'position' => $e->position,
                ]],
            ];
        }
    }

    public function substituteVariables(string $formula, array $variables): string
    {
        return preg_replace_callback('/\{([a-z][a-z0-9_]*)\}/', function ($matches) use ($variables) {
            $name = $matches[1];
            if (!array_key_exists($name, $variables)) {
                return $matches[0];
            }

            return $this->formatResult((float) $variables[$name]);
        }, $formula);
    }

    public function formatResult(float $value): string
    {
        $formatted = rtrim(rtrim(sprintf('%.10F', $value), '0'), '.');
        return $formatted === '' || $formatted === '-0' ? '0' : $formatted;
    }

    private function registerDefaultFunctions(): void
    {
        $handlers = [
            new RoundFunction(),
            new TruncFunction(),
            new AbsFunction(),
            new MinFunction(),
            new MaxFunction(),
            new PowerFunction(),
            new SqrtFunction(),
            new LogFunction(),
            new FloorFunction(),
            new CeilFunction(),
        ];

        foreach ($handlers as $handler) {
            $this->registry->register($handler);
        }
    }
}
