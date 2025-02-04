<?php declare(strict_types=1);
/*
 * This file is part of PHPUnit.
 *
 * (c) Sebastian Bergmann <sebastian@phpunit.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PHPUnit\Framework\MockObject;

use function array_map;
use function explode;
use function get_class;
use function implode;
use function is_object;
use function sprintf;
use function str_contains;
use function str_starts_with;
use function strtolower;
use function substr;
use Doctrine\Instantiator\Instantiator;
use PHPUnit\Framework\SelfDescribing;
use PHPUnit\Util\Type;
use SebastianBergmann\Exporter\Exporter;
use stdClass;

/**
 * @internal This class is not covered by the backward compatibility promise for PHPUnit
 */
final class Invocation implements SelfDescribing
{
    private string $className;

    private string $methodName;

    private array $parameters;

    private string $returnType;

    private bool $isReturnTypeNullable = false;

    private bool $proxiedCall;

    private object $object;

    public function __construct(string $className, string $methodName, array $parameters, string $returnType, object $object, bool $cloneObjects = false, bool $proxiedCall = false)
    {
        $this->className   = $className;
        $this->methodName  = $methodName;
        $this->parameters  = $parameters;
        $this->object      = $object;
        $this->proxiedCall = $proxiedCall;

        if (strtolower($methodName) === '__tostring') {
            $returnType = 'string';
        }

        if (str_starts_with($returnType, '?')) {
            $returnType                 = substr($returnType, 1);
            $this->isReturnTypeNullable = true;
        }

        $this->returnType = $returnType;

        if (!$cloneObjects) {
            return;
        }

        foreach ($this->parameters as $key => $value) {
            if (is_object($value)) {
                $this->parameters[$key] = $this->cloneObject($value);
            }
        }
    }

    public function getClassName(): string
    {
        return $this->className;
    }

    public function getMethodName(): string
    {
        return $this->methodName;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * @throws RuntimeException
     */
    public function generateReturnValue(): mixed
    {
        if ($this->isReturnTypeNullable || $this->proxiedCall) {
            return null;
        }

        $returnType = $this->returnType;

        if (str_contains($returnType, '|')) {
            $types      = explode('|', $returnType);
            $returnType = $types[0];

            foreach ($types as $type) {
                if ($type === 'null') {
                    return null;
                }
            }
        }

        switch (strtolower($returnType)) {
            case '':
            case 'mixed':
            case 'void':
                return null;

            case 'string':
                return '';

            case 'float':
                return 0.0;

            case 'int':
                return 0;

            case 'bool':
                return false;

            case 'array':
                return [];

            case 'static':
                return (new Instantiator)->instantiate(get_class($this->object));

            case 'object':
                return new stdClass;

            case 'callable':
            case 'closure':
                return static function (): void {
                };

            case 'traversable':
            case 'generator':
            case 'iterable':
                $generator = static function (): \Generator {
                    yield;
                };

                return $generator();

            default:
                return (new Generator)->getMock($this->returnType, [], [], '', false);
        }
    }

    public function toString(): string
    {
        $exporter = new Exporter;

        return sprintf(
            '%s::%s(%s)%s',
            $this->className,
            $this->methodName,
            implode(
                ', ',
                array_map(
                    [$exporter, 'shortenedExport'],
                    $this->parameters
                )
            ),
            $this->returnType ? sprintf(': %s', $this->returnType) : ''
        );
    }

    public function getObject(): object
    {
        return $this->object;
    }

    private function cloneObject(object $original): object
    {
        if (Type::isCloneable($original)) {
            return clone $original;
        }

        return $original;
    }
}
