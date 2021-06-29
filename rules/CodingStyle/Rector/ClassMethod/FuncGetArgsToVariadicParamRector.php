<?php

declare(strict_types=1);

namespace Rector\CodingStyle\Rector\ClassMethod;

use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use Rector\Core\Rector\AbstractRector;
use Rector\Core\ValueObject\PhpVersionFeature;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * @changelog https://3v4l.org/d4tBd
 *
 * @see \Rector\Tests\CodingStyle\Rector\ClassMethod\FuncGetArgsToVariadicParamRector\FuncGetArgsToVariadicParamRectorTest
 */
final class FuncGetArgsToVariadicParamRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition('Refactor func_get_args() in to a variadic param', [
            new CodeSample(
                <<<'CODE_SAMPLE'
function run()
{
    $args = \func_get_args();
}
CODE_SAMPLE

                ,
                <<<'CODE_SAMPLE'
function run(...$args)
{
}
CODE_SAMPLE
            ),
        ]);
    }

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [ClassMethod::class, Function_::class, Closure::class];
    }

    /**
     * @param ClassMethod|Function_|Closure $node
     */
    public function refactor(Node $node): ?Node
    {
        if (! $this->isAtLeastPhpVersion(PhpVersionFeature::VARIADIC_PARAM)) {
            return null;
        }

        if ($node->params !== []) {
            return null;
        }

        $assign = $this->matchFuncGetArgsVariableAssign($node);
        if (! $assign instanceof Assign) {
            return null;
        }

        if ($assign->var instanceof Variable) {
            $variableName = $this->getName($assign->var);
            if ($variableName === null) {
                return null;
            }

            $this->removeNode($assign);
        } else {
            $variableName = 'args';
            $assign->expr = new Variable('args');
        }

        $param = $this->createVariadicParam($variableName);
        $variableParam = $param->var;
        if ($variableParam instanceof Variable && $this->hasFunctionOrClosureInside($node, $variableParam)) {
            return null;
        }

        $node->params[] = $param;
        return $node;
    }

    private function hasFunctionOrClosureInside(
        ClassMethod | Function_ | Closure $functionLike,
        Variable $variable
    ): bool
    {
        if ($functionLike->stmts === null) {
            return false;
        }

        return (bool) $this->betterNodeFinder->findFirst($functionLike->stmts, function (Node $node) use (
            $variable
        ): bool {
            if (! $node instanceof Closure && ! $node instanceof Function_) {
                return false;
            }

            if ($node->params !== []) {
                return false;
            }

            $assign = $this->matchFuncGetArgsVariableAssign($node);
            if (! $assign instanceof Assign) {
                return false;
            }

            return $this->nodeComparator->areNodesEqual($assign->var, $variable);
        });
    }

    private function matchFuncGetArgsVariableAssign(ClassMethod | Function_ | Closure $functionLike): ?Assign
    {
        /** @var Assign[] $assigns */
        $assigns = $this->betterNodeFinder->findInstanceOf((array) $functionLike->stmts, Assign::class);

        foreach ($assigns as $assign) {
            if (! $assign->expr instanceof FuncCall) {
                continue;
            }

            if (! $this->isName($assign->expr, 'func_get_args')) {
                continue;
            }

            return $assign;
        }

        return null;
    }

    private function createVariadicParam(string $variableName): Param
    {
        $variable = new Variable($variableName);
        return new Param($variable, null, null, false, true);
    }
}
