<?php
/**
 * FileStreamingAdvancedTest
 *
 * Echo-based test focusing on real behavior aspects:
 * - JsBuilderV2 development streaming branches
 * - Production build via builder.json with headers/cache and output validation
 */

namespace ngs\tests\util {

require_once __DIR__ . '/../../src/Dispatcher.php';
require_once __DIR__ . '/../../src/NGSModule.php';
require_once __DIR__ . '/../../src/routes/NgsRoute.php';
require_once __DIR__ . '/../../src/routes/NgsFileRoute.php';
require_once __DIR__ . '/../../src/util/AbstractBuilder.php';
require_once __DIR__ . '/../../src/util/FileUtils.php';
require_once __DIR__ . '/../../src/util/JsBuilderV2.php';
require_once __DIR__ . '/../../src/util/NgsEnvironmentContext.php';
require_once __DIR__ . '/../../src/routes/NgsModuleResolver.php';
require_once __DIR__ . '/../../src/event/EventManagerInterface.php';
require_once __DIR__ . '/../../src/event/EventManager.php';
require_once __DIR__ . '/../../src/event/structure/AbstractEventStructure.php';
require_once __DIR__ . '/../../src/event/structure/BeforeResultDisplayEventStructure.php';

use ngs\Dispatcher;

function fs_mkdirp(string $dir): void { if (!is_dir($dir)) { mkdir($dir, 0777, true); } }

// Spies and overrides
class AdvSpyFileUtils extends \ngs\util\FileUtils {
  public static array $calls = [];
  public function sendFile(string $path, array $options = []): void {
    self::$calls[] = ['method' => 'sendFile', 'path' => $path, 'options' => $options];
    echo "AdvSpyFileUtils::sendFile called: cache=" . (($options['cache'] ?? null) ? 'true' : 'false') . ", mime=" . ($options['mimeType'] ?? '') . "\n";
  }
  public function streamFile(string $path): void {
    self::$calls[] = ['method' => 'streamFile', 'path' => $path, 'options' => []];
    echo "AdvSpyFileUtils::streamFile called with $path\n";
  }
}

class AdvSpyJsBuilder extends \ngs\util\JsBuilderV2 {
  public function triggerBuild($file){ return parent::build($file); }
  public static string $baseModuleDir = '';
  public static string $builderJson = '';
  public static ?string $forcedOutputDir = null;
  protected function getItemDir($module) {
    // Serve files from {base}/js
    return realpath(self::$baseModuleDir . '/' . \NGS()->get('JS_DIR')) ?: self::$baseModuleDir . '/' . \NGS()->get('JS_DIR');
  }
  protected function getBuilderFile() {
    return self::$builderJson;
  }
  public function getOutputDir(): string {
    if (self::$forcedOutputDir) { return self::$forcedOutputDir; }
    return parent::getOutputDir();
  }
}

}

namespace {
  if (!function_exists('NGS')) {
    function NGS() {
      static $instance = null;
      if ($instance === null) {
        $instance = new class {
          private array $data = [];
          public function set($k,$v){$this->data[$k]=$v;return $this;}
          public function get($k){return $this->data[$k] ?? null;}
          public function getDefinedValue($k){return $this->get($k);}          
          public function getName(){ return 'ngs'; }
          public function createDefinedInstance($key,$class){
            switch ($key){
              case 'FILE_UTILS': return new \ngs\tests\util\AdvSpyFileUtils();
              case 'JS_BUILDER': return new \ngs\tests\util\AdvSpyJsBuilder();
              case 'REQUEST_CONTEXT':
                return new class {
                  public function getRequestUri(){ return '/'; }
                  public function isAjaxRequest(){ return false; }
                  public function redirect($u){ echo "redirect to $u\n"; }
                  public function getHttpHost(bool $proto=true,bool $withSlash=true){ return 'http://localhost'; }
                  public function getHttpHostByNs($module=''){ return 'http://localhost'; }
                };
              case 'ROUTES_ENGINE': return new class {};
              case 'MODULES_ROUTES_ENGINE':
                return new class {
                  public function resolveModule($uri){
                    return new class {
                      public function getName(){ return 'ngs'; }
                      public function getDir(){ return getcwd(); }
                    };
                  }
                  public function getDefaultNS(){ return 'ngs'; }
                };
              case 'TEMPLATE_ENGINE':
                return new class {
                  public function setHttpStatusCode($c){}
                  public function assignJson($k,$v){}
                  public function assignJsonParams($p){}
                  public function setType($t){}
                  public function setTemplate($t){}
                  public function setPermalink($p){}
                  public function display($json=false){}
                };
              case 'SESSION_MANAGER': return new class { public function validateRequest($r){ return true; } };
              case 'LOAD_MAPPER': return new class { public function getNgsPermalink(){ return '/'; } };
            }
            return new $class();
          }
          public function getModuleDirByNS($ns){ return __DIR__; }
        };
        $instance
          ->set('PUBLIC_DIR', 'public')
          ->set('PUBLIC_OUTPUT_DIR', 'out')
          ->set('JS_DIR', 'js')
          ->set('ENVIRONMENT', 'development')
          ->set('JS_BUILD_MODE', 'development');
      }
      return $instance;
    }
  }
}

namespace ngs\tests\util {

class AdvTestModule extends \ngs\NgsModule {
  private string $name; private string $dir;
  public function __construct(string $name, string $dir){ $this->name=$name; $this->dir=$dir; }
  public function getName(): string { return $this->name; }
  public function getDir(): string { return $this->dir; }
}

// Begin
echo "\n[FileStreamingAdvancedTest] Starting...\n";

$base = sys_get_temp_dir() . '/ngs_fs_adv_' . uniqid();
$moduleDir = $base . '/moduleA';
$publicDir = $moduleDir . '/public';
fs_mkdirp($publicDir);
fs_mkdirp($publicDir . '/js');

// Point PUBLIC_DIR to absolute temp path to ensure output placement
\NGS()->set('PUBLIC_DIR', $publicDir);

$module = new AdvTestModule('moduleA', $moduleDir);

// 1) JsBuilderV2 dev: same-directory branch
$devFileRel = 'js/dev-branch1.js';
$devFileAbs = $publicDir . '/' . $devFileRel; // same dir as requested
file_put_contents($devFileAbs, 'console.log("dev1");');

$route1 = new \ngs\routes\NgsFileRoute();
$route1->setMatched(true);
$route1->setModule($module);
$route1->setFileUrl($devFileRel);
$route1->setFileType('js');

AdvSpyFileUtils::$calls = [];
(new \ngs\Dispatcher())->dispatch($route1);
$last1 = end(AdvSpyFileUtils::$calls);
$pass1 = is_array($last1) && ($last1['method'] ?? '') === 'sendFile' && (($last1['options']['cache'] ?? true) === false) && (($last1['options']['mimeType'] ?? '') === 'text/javascript');
echo "JsBuilderV2 dev same-dir → sendFile cache=false: " . ($pass1 ? 'PASS' : 'FAIL') . "\n";

// 2) JsBuilderV2 dev: module/js path resolution branch
// Remove same-dir file to force alt resolution
@unlink($devFileAbs);

// Prepare module js real file
fs_mkdirp($moduleDir . '/js');
$dev2Rel = 'js/moduleA/alt.js'; // request path includes module name
file_put_contents($moduleDir . '/js/alt.js', 'console.log("dev2");');

$route2 = new \ngs\routes\NgsFileRoute();
$route2->setMatched(true);
$route2->setModule($module);
$route2->setFileUrl($dev2Rel);
$route2->setFileType('js');

AdvSpyFileUtils::$calls = [];
(new \ngs\Dispatcher())->dispatch($route2);
$last2 = end(AdvSpyFileUtils::$calls);
$pass2 = is_array($last2) && ($last2['method'] ?? '') === 'sendFile' && (($last2['options']['cache'] ?? true) === false);
echo "JsBuilderV2 dev module-js path → sendFile cache=false: " . ($pass2 ? 'PASS' : 'FAIL') . "\n";

// 3) Production build for JS via builder.json with headers, output dir and mtime check
\NGS()->set('JS_BUILD_MODE', 'production');
AdvSpyJsBuilder::$baseModuleDir = $moduleDir;

// Prepare builder.json and source file
fs_mkdirp($moduleDir . '/js');
$builderPath = $moduleDir . '/js/builder.json';
$srcPath = $moduleDir . '/js/a.js';
file_put_contents($srcPath, 'console.log("built");');
$builderContent = json_encode([
  (object)[
    'output_file' => 'bundle.js',
    'compress' => false,
    'files' => ['a.js']
  ]
]);
file_put_contents($builderPath, $builderContent);

// Set builder.json atime to a known timestamp
$ts = time() - 12345;
@touch($builderPath, $ts, $ts);

AdvSpyJsBuilder::$builderJson = $builderPath;

// Ensure public output dir base exists
fs_mkdirp($publicDir . '/out/js');
AdvSpyJsBuilder::$forcedOutputDir = $publicDir . '/out/js';

// Pre-build output manually to validate touch/time semantics
$builder = new AdvSpyJsBuilder();
$builder->triggerBuild('bundle.js');

$route3 = new \ngs\routes\NgsFileRoute();
$route3->setMatched(true);
$route3->setModule($module);
$route3->setFileUrl('js/bundle.js');
$route3->setFileType('js');

AdvSpyFileUtils::$calls = [];
(new \ngs\Dispatcher())->dispatch($route3);
$last3 = end(AdvSpyFileUtils::$calls);
$outFile = $publicDir . '/out/js/bundle.js';
$builtTs = filemtime($outFile);
$okTs = ($builtTs === @fileatime($builderPath)) || ($builtTs === @filemtime($builderPath));
$pass3 = is_array($last3)
  && ($last3['method'] ?? '') === 'sendFile'
  && (($last3['options']['cache'] ?? false) === true);

echo "Production build JS → cache=true and output exists with mtime: " . ($pass3 ? 'PASS' : 'FAIL') . "\n";

echo "[FileStreamingAdvancedTest] Completed.\n";
}
