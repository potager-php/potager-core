<?php

use Potager\App;
use Potager\Console\Commands\MakeCommandCommand;
use Potager\Console\Commands\MakeControllerCommand;
use Potager\Console\Commands\MakeMiddlewareCommand;
use Potager\Console\Commands\MakeModelCommand;
use Potager\Console\Commands\MakePipeCommand;
use Potager\Console\IO\ArgvInput;
use Potager\Console\IO\Output;
use Potager\Console\Kernel;

describe('Generator Commands & Template Method', function () {
    beforeEach(function () {
        $this->tempDir = sys_get_temp_dir() . '/potager_generator_test_' . uniqid();
        mkdir($this->tempDir . '/app', 0755, true);

        $this->app = App::create($this->tempDir);
        $this->kernel = new Kernel($this->app->getContainer());
        $this->kernel->registerBuiltins();
    });

    afterEach(function () {
        // Cleanup temp directory
        $cleanup = function ($dir) use (&$cleanup) {
            if (!is_dir($dir)) return;
            $items = array_diff(scandir($dir), ['.', '..']);
            foreach ($items as $item) {
                $path = $dir . '/' . $item;
                is_dir($path) ? $cleanup($path) : unlink($path);
            }
            rmdir($dir);
        };
        $cleanup($this->tempDir);
    });

    it('generates a controller in the default namespace', function () {
        $input = new ArgvInput(['farmer', 'make:controller', 'UserController']);
        $output = new Output(false);

        ob_start();
        $code = $this->kernel->handle($input, $output);
        $content = ob_get_clean();

        expect($code)->toBe(0);
        expect($content)->toContain('Controller [App\Controllers\UserController] created successfully.');

        $filePath = $this->tempDir . '/app/Controllers/UserController.php';
        expect(file_exists($filePath))->toBeTrue();

        $fileContent = file_get_contents($filePath);
        expect($fileContent)->toContain('namespace App\Controllers;');
        expect($fileContent)->toContain('class UserController extends Controller');
    });

    it('generates a controller with sub-namespaces properly mapped', function () {
        $input = new ArgvInput(['farmer', 'make:controller', 'Api/V1/OrderController']);
        $output = new Output(false);

        ob_start();
        $code = $this->kernel->handle($input, $output);
        $content = ob_get_clean();

        expect($code)->toBe(0);
        expect($content)->toContain('Controller [App\Controllers\Api\V1\OrderController] created successfully.');

        $filePath = $this->tempDir . '/app/Controllers/Api/V1/OrderController.php';
        expect(file_exists($filePath))->toBeTrue();

        $fileContent = file_get_contents($filePath);
        expect($fileContent)->toContain('namespace App\Controllers\Api\V1;');
        expect($fileContent)->toContain('class OrderController extends Controller');
    });

    it('appends required suffix if missing', function () {
        $input = new ArgvInput(['farmer', 'make:command', 'SendNewsletter']);
        $output = new Output(false);

        ob_start();
        $code = $this->kernel->handle($input, $output);
        $content = ob_get_clean();

        expect($code)->toBe(0);
        expect($content)->toContain('Command [App\Commands\SendNewsletterCommand] created successfully.');

        $filePath = $this->tempDir . '/app/Commands/SendNewsletterCommand.php';
        expect(file_exists($filePath))->toBeTrue();

        $fileContent = file_get_contents($filePath);
        expect($fileContent)->toContain('class SendNewsletterCommand extends Command');
        expect($fileContent)->toContain("protected string \$name = 'send:newsletter';");
    });

    it('prevents overwriting without --force and allows with --force', function () {
        $filePath = $this->tempDir . '/app/Models/Product.php';
        mkdir(dirname($filePath), 0755, true);
        file_put_contents($filePath, '// Custom existing content');

        // First attempt without force
        $input = new ArgvInput(['farmer', 'make:model', 'Product']);
        $output = new Output(false);

        ob_start();
        $code = $this->kernel->handle($input, $output);
        $content = ob_get_clean();

        expect($code)->toBe(1);
        expect($content)->toContain('Use --force to overwrite.');
        expect(file_get_contents($filePath))->toBe('// Custom existing content');

        // Second attempt with force
        $inputForce = new ArgvInput(['farmer', 'make:model', 'Product', '--force']);
        ob_start();
        $codeForce = $this->kernel->handle($inputForce, $output);
        $contentForce = ob_get_clean();

        expect($codeForce)->toBe(0);
        expect($contentForce)->toContain('Model [App\Models\Product] created successfully.');
        expect(file_get_contents($filePath))->toContain('class Product extends Model');
    });

    it('generates a console pipe via make:pipe', function () {
        $input = new ArgvInput(['farmer', 'make:pipe', 'SecurityAudit']);
        $output = new Output(false);

        ob_start();
        $code = $this->kernel->handle($input, $output);
        $content = ob_get_clean();

        expect($code)->toBe(0);
        expect($content)->toContain('Pipe [App\Pipes\SecurityAuditPipe] created successfully.');

        $filePath = $this->tempDir . '/app/Pipes/SecurityAuditPipe.php';
        expect(file_exists($filePath))->toBeTrue();

        $fileContent = file_get_contents($filePath);
        expect($fileContent)->toContain('namespace App\Pipes;');
        expect($fileContent)->toContain('class SecurityAuditPipe implements PipeInterface');
    });

    it('generates a GeneratorCommand subclass when --generator flag is provided', function () {
        $input = new ArgvInput(['farmer', 'make:command', 'MakeService', '--generator']);
        $output = new Output(false);

        ob_start();
        $code = $this->kernel->handle($input, $output);
        $content = ob_get_clean();

        expect($code)->toBe(0);
        expect($content)->toContain('Command [App\Commands\MakeServiceCommand] created successfully.');

        $filePath = $this->tempDir . '/app/Commands/MakeServiceCommand.php';
        expect(file_exists($filePath))->toBeTrue();

        $fileContent = file_get_contents($filePath);
        expect($fileContent)->toContain('use Potager\Console\GeneratorCommand;');
        expect($fileContent)->toContain('class MakeServiceCommand extends GeneratorCommand');
        expect($fileContent)->toContain("protected string \$name = 'make:service';");
        expect($fileContent)->toContain("protected string \$type = 'Service';");
        expect($fileContent)->toContain("return \$rootNamespace . '\Services';");
    });
});
