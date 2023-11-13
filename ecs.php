<?php

declare(strict_types=1);

use PhpCsFixer\Fixer\ControlStructure\YodaStyleFixer;
use PhpCsFixer\Fixer\Operator\NotOperatorWithSuccessorSpaceFixer;
use PhpCsFixer\Fixer\Strict\DeclareStrictTypesFixer;
use Symplify\CodingStandard\Fixer\LineLength\LineLengthFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;
use Symplify\EasyCodingStandard\ValueObject\Set\SetList;

return function (ECSConfig $ecsConfig): void {
    $ecsConfig->paths([
        __DIR__ . '/src',
    ]);

    $ecsConfig->rules([
        DeclareStrictTypesFixer::class,
        LineLengthFixer::class
    ]);

    $ecsConfig->skip([
        NotOperatorWithSuccessorSpaceFixer::class
    ]);

    $ecsConfig->sets([
         SetList::SPACES,
         SetList::ARRAY,
         SetList::DOCBLOCK,
         SetList::NAMESPACES,
         SetList::COMMENTS,
         SetList::PSR_12,
    ]);

    $ecsConfig->ruleWithConfiguration(YodaStyleFixer::class, [
        'equal' => false,
        'identical' => false,
        'less_and_greater' => false,
    ]);
};
