<?php
/**
 * This source file is subject to the GNU General Public License version 3 (GPLv3)
 * For the full copyright and license information, please view the LICENSE.md and gpl-3.0.txt
 * files that are distributed with this source code.
 *
 * @license    https://choosealicense.com/licenses/gpl-3.0/ GNU General Public License v3.0
 * @copyright  Copyright (c) 2023 Brand Oriented sp. z o.o. (https://brandoriented.pl)
 * @copyright  Copyright (c) 2021 CI HUB GmbH (https://ci-hub.com)
 */
$fileHeaderComment = <<<'EOF'
    This source file is subject to the GNU General Public License version 3 (GPLv3)
    For the full copyright and license information, please view the LICENSE.md and gpl-3.0.txt
    files that are distributed with this source code.

    @license    https://choosealicense.com/licenses/gpl-3.0/ GNU General Public License v3.0
    @copyright  Copyright (c) 2023 Brand Oriented sp. z o.o. (https://brandoriented.pl)
    @copyright  Copyright (c) 2021 CI HUB GmbH (https://ci-hub.com)
    EOF;

return (new PhpCsFixer\Config())
    ->setRules([
        '@PHP80Migration' => true,
        '@PHP81Migration' => true,
        '@PHP82Migration' => true,
        '@PHPUnit84Migration:risky' => true,
        '@PSR12' => true,
        '@PSR2' => true,
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'strict_param' => true,
        'mb_str_functions' => true,
        'protected_to_private' => false,
        'native_constant_invocation' => [
            'strict' => false,
        ],
        'nullable_type_declaration_for_default_null_value' => [
            'use_nullable_type_declaration' => false,
        ],
        'header_comment' => [
            'header' => $fileHeaderComment,
            'separate' => 'none',
            'comment_type' => 'PHPDoc',
            'location' => 'after_open',
        ],
        'modernize_strpos' => true,
    ])
    ->setRiskyAllowed(true)
    ->setFinder(PhpCsFixer\Finder::create()
        ->in(__DIR__.'/src/')
    )
    ->setFinder(
        (new PhpCsFixer\Finder())
            ->in(__DIR__.'/tests')
            ->append([__FILE__])
            ->notPath('#/Fixtures/#')
    )
    ->setUsingCache(false);
