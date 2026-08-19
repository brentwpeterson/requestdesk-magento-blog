<?php
/**
 * Unit-test bootstrap.
 *
 * Magento generates its *Factory classes at runtime into generated/code, so they
 * exist in an installed store but not in a bare composer tree. This suite is
 * deliberately runnable without a Magento installation - that is the whole point
 * of it being fast enough for CI - which means any factory a class under test
 * type-hints has to be declared here, or PHPUnit cannot even build a mock of it.
 *
 * These are stand-ins for code generation, not test doubles. Behaviour still
 * comes from the mocks the tests configure.
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

$generatedFactories = [
    'Magento\Catalog\Model\CategoryFactory',
];

foreach ($generatedFactories as $factory) {
    if (class_exists($factory)) {
        continue;
    }

    $parts = explode('\\', $factory);
    $shortName = array_pop($parts);
    $namespace = implode('\\', $parts);

    eval(
        sprintf(
            'namespace %s; class %s { public function create(array $data = []) { return null; } }',
            $namespace,
            $shortName
        )
    );
}
