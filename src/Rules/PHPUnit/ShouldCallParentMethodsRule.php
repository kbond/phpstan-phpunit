<?php declare(strict_types = 1);

namespace PHPStan\Rules\PHPUnit;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassMethodNode;
use PHPStan\Rules\RuleErrorBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @implements \PHPStan\Rules\Rule<InClassMethodNode>
 */
class ShouldCallParentMethodsRule implements \PHPStan\Rules\Rule
{

	public function getNodeType(): string
	{
		return InClassMethodNode::class;
	}

	public function processNode(Node $node, Scope $scope): array
	{
		if ($scope->getClassReflection() === null) {
			return [];
		}

		if (!$scope->getClassReflection()->isSubclassOf(TestCase::class)) {
			return [];
		}

		$parentClass = $scope->getClassReflection()->getParentClass();

		if ($parentClass === false) {
			return [];
		}

		if ($parentClass->getName() === TestCase::class) {
			return [];
		}

		$methodName = $node->getOriginalNode()->name->name;

		if (!in_array(strtolower($methodName), ['setup', 'teardown'], true)) {
			return [];
		}

		$hasParentCall = $this->hasParentClassCall($node->getOriginalNode()->getStmts(), strtolower($methodName));

		if (!$hasParentCall) {
			return [
				RuleErrorBuilder::message(
					sprintf('Missing call to parent::%s() method.', $methodName)
				)->build(),
			];
		}

		return [];
	}

	/**
	 * @param Node\Stmt[]|null $stmts
	 * @param string           $methodName
	 *
	 * @return bool
	 */
	private function hasParentClassCall(?array $stmts, string $methodName): bool
	{
		if ($stmts === null) {
			return false;
		}

		foreach ($stmts as $stmt) {
			if (! $stmt instanceof Node\Stmt\Expression) {
				continue;
			}

			if (! $stmt->expr instanceof Node\Expr\StaticCall) {
				continue;
			}

			if (! $stmt->expr->class instanceof Node\Name) {
				continue;
			}

			$class = (string) $stmt->expr->class;

			if (strtolower($class) !== 'parent') {
				continue;
			}

			if (! $stmt->expr->name instanceof Node\Identifier) {
				continue;
			}

			if (strtolower($stmt->expr->name->name) === $methodName) {
				return true;
			}
		}

		return false;
	}

}
