<?php defined('OPTV') or die();
/**
 * Plates Template Engine Factory
 *
 * Sets up the Plates engine with:
 * - content/ as the template root (templates stay where they live)
 * - A custom path resolver that reproduces the include_custom() override
 *   convention so existing parliament-instance overrides keep working:
 *       custom/content/{name}.{lang}.php   ← language-specific override
 *       custom/content/{name}.php          ← generic override
 *       content/{name}.php                 ← default
 * - Shared data available to all templates (replaces implicit globals)
 */

require_once(__DIR__ . '/../../vendor/autoload.php');

use League\Plates\Engine;
use League\Plates\Template\Name;
use League\Plates\Template\ResolveTemplatePath;
use League\Plates\Exception\TemplateNotFound;

/**
 * Resolves a Plates template name to a file path using the same priority chain
 * as include_custom() (modules/utilities/functions.php). The current language is
 * read live from $_SESSION['lang'] on every resolution, exactly like the legacy
 * helper, so language switching mid-session resolves the right override.
 */
final class OptvResolveTemplatePath implements ResolveTemplatePath
{
    private string $customDir;

    public function __construct(string $customDir)
    {
        $this->customDir = $customDir;
    }

    public function __invoke(Name $name): string
    {
        $template = $name->getName();
        $ext = $name->getEngine()->getFileExtension() ?? 'php';
        $lang = $_SESSION['lang'] ?? 'de';

        $candidates = [
            $this->customDir . '/' . $template . '.' . $lang . '.' . $ext,
            $this->customDir . '/' . $template . '.' . $ext,
        ];
        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        // Fall back to the default location under content/.
        $default = $name->getPath();
        if (is_file($default)) {
            return $default;
        }

        throw new TemplateNotFound(
            $template,
            array_merge($candidates, [$default]),
            'The template "' . $template . '" was not found (checked custom overrides and content/).'
        );
    }
}

/**
 * Build and configure the shared Plates engine.
 */
function createPlatesEngine(): Engine
{
    global $config, $lang, $langJSONString, $color_scheme, $isMobile, $acceptLang, $paramStr, $isResult;

    $projectRoot = realpath(__DIR__ . '/../../');
    $defaultDir = $projectRoot . '/content';
    $customDir = $projectRoot . '/custom/content';

    $engine = new Engine($defaultDir, 'php');
    $engine->setResolveTemplatePath(new OptvResolveTemplatePath($customDir));

    // Shared data available in EVERY template (head/header/footer/components).
    // These replace the ambient globals the old templates relied on.
    $engine->addData([
        'config' => $config,
        'lang' => $lang ?? ($_SESSION['lang'] ?? 'de'),
        'langJSONString' => $langJSONString ?? '{}',
        'color_scheme' => $color_scheme ?? 'light',
        'isMobile' => $isMobile ?? false,
        'acceptLang' => $acceptLang ?? [],
        'paramStr' => $paramStr ?? '',
        'isResult' => $isResult ?? false,
        'isLoggedIn' => !empty($_SESSION['login']),
        'sessionData' => $_SESSION['userdata'] ?? [],
    ]);

    return $engine;
}
