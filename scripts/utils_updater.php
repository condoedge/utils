<?php

/**
 * Script to update condoedge/utils, increment versions and auto push
 * 
 * Usage: php utils_updater.php [directory]
 * Example: php utils_updater.php C:\Users\jkend\Documents\kompo
 */

class PackageUpdater
{
    private string $baseDir;
    private string $latestUtilsVersion = '';
    private array $stats = ['processed' => 0, 'projects' => 0, 'packages' => 0, 'updated' => 0, 'errors' => 0];

    public function __construct(string $baseDir = null)
    {
        // Use provided directory or default
        if ($baseDir) {
            $this->baseDir = rtrim($baseDir, '/\\');
        } else {
            // Default directories based on OS
            if (PHP_OS_FAMILY === 'Windows') {
                $this->baseDir = 'C:\Users\jkend\Documents\kompo';
            } else {
                $this->baseDir = '/c/Users/jkend/Documents/kompo';
            }
        }
    }
    /**
     * Automatically detect the latest version of condoedge/utils
     */
    private function detectLatestUtilsVersion(): void
    {
        $this->log("🔍 Detecting latest version of condoedge/utils...", 'warning');

        // Method 1: Packagist API
        $this->latestUtilsVersion = $this->getVersionFromPackagist();

        if ($this->latestUtilsVersion) {
            $this->log("✓ Detected from Packagist: {$this->latestUtilsVersion}", 'success');
            return;
        }

        // Method 2: Search in local composer.lock
        $this->latestUtilsVersion = $this->getVersionFromLocalLock();

        if ($this->latestUtilsVersion) {
            $this->log("✓ Detected from local composer.lock: {$this->latestUtilsVersion}", 'success');
            return;
        }

        // Fallback
        $this->latestUtilsVersion = '^0.2.0';
        $this->log("⚠️  Using default version: {$this->latestUtilsVersion}", 'warning');
    }

    /**
     * Get version from Packagist API
     */
    private function getVersionFromPackagist(): ?string
    {
        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'user_agent' => 'PackageUpdater/1.0'
                ]
            ]);

            $response = @file_get_contents('https://packagist.org/packages/condoedge/utils.json', false, $context);

            if ($response) {
                $data = json_decode($response, true);

                if (isset($data['package']['versions'])) {
                    $versions = array_keys($data['package']['versions']);                    // Filter stable versions (no dev- or alpha/beta)
                    $stableVersions = array_filter($versions, function ($v) {
                        return !str_starts_with($v, 'dev-') &&
                            !str_contains($v, 'alpha') &&
                            !str_contains($v, 'beta') &&
                            !str_contains($v, 'RC');
                    });

                    if (!empty($stableVersions)) {
                        // Sort by version and take the latest
                        usort($stableVersions, 'version_compare');
                        $latest = end($stableVersions);
                        return "^{$latest}";
                    }
                }
            }
        } catch (Exception $e) {
            // Ignore network errors
        }

        return null;
    }

    /**
     * Get version from local composer.lock
     */
    private function getVersionFromLocalLock(): ?string
    {
        $iterator = new DirectoryIterator($this->baseDir);

        foreach ($iterator as $item) {
            if ($item->isDot() || !$item->isDir()) continue;

            $lockFile = $item->getPathname() . '/composer.lock';

            if (file_exists($lockFile)) {
                $lockContent = file_get_contents($lockFile);
                $lockData = json_decode($lockContent, true);

                if (isset($lockData['packages'])) {
                    foreach ($lockData['packages'] as $package) {
                        if (
                            $package['name'] === 'condoedge/utils' &&
                            !str_starts_with($package['version'], 'dev-')
                        ) {
                            return "^{$package['version']}";
                        }
                    }
                }
            }
        }

        return null;
    }
    /**
     * Detect if it's a project (not a package)
     */
    private function isProject(string $packageDir): bool
    {
        $composerPath = $packageDir . '/composer.json';

        if (!file_exists($composerPath)) {
            return false;
        }

        $content = file_get_contents($composerPath);
        $composer = json_decode($content, true);

        if (!$composer) {
            return false;
        }

        // // 1. Tiene "type": "project"
        // if (isset($composer['type']) && $composer['type'] === 'project') {
        //     return true;
        // }

        // // 2. Tiene dependencias típicas de aplicación
        // $appDependencies = [
        //     'laravel/framework',
        //     'laravel/laravel',
        //     'symfony/console'
        // ];

        // if (isset($composer['require'])) {
        //     foreach ($appDependencies as $dep) {
        //         if (isset($composer['require'][$dep])) {
        //             return true;
        //         }
        //     }
        // }        // 3. Has typical application scripts
        if (isset($composer['scripts'])) {
            $appScripts = ['serve', 'artisan', 'dev', 'build'];
            foreach ($appScripts as $script) {
                if (isset($composer['scripts'][$script])) {
                    return true;
                }
            }
        }

        // 4. Application directory structure
        $appDirs = ['app', 'config'];
        $appFiles = ['artisan', '.env.example'];

        $hasAppDirs = true;
        foreach ($appDirs as $dir) {
            if (!is_dir($packageDir . '/' . $dir)) {
                $hasAppDirs = false;
                break;
            }
        }

        if ($hasAppDirs) {
            foreach ($appFiles as $file) {
                if (file_exists($packageDir . '/' . $file)) {
                    return true;
                }
            }
        }

        // 5. Doesn't have typical package "name" (vendor/package)
        if (!isset($composer['name']) || !str_contains($composer['name'], '/')) {
            return true;
        }

        return false;
    }    /**
     * Execute command and capture output
     */
    private function exec(string $command, string $workingDir = null): array
    {
        $originalDir = getcwd();

        if ($workingDir) {
            if (!chdir($workingDir)) {
                return [
                    'output' => ["Failed to change directory to: {$workingDir}"],
                    'success' => false,
                    'code' => -1
                ];
            }
        }

        $output = [];
        $returnCode = 0;
        
        // For Windows, ensure proper escaping and use cmd /c
        if (PHP_OS_FAMILY === 'Windows') {
            $command = 'cmd /c "' . $command . '"';
        }
        
        exec($command . ' 2>&1', $output, $returnCode);

        if ($workingDir) {
            chdir($originalDir);
        }

        return [
            'output' => $output,
            'success' => $returnCode === 0,
            'code' => $returnCode
        ];
    }
    /**
     * Log with colors
     */
    private function log(string $message, string $type = 'info'): void
    {
        $colors = [
            'info' => "\033[0;37m",     // White
            'success' => "\033[0;32m",  // Green
            'warning' => "\033[1;33m",  // Yellow
            'error' => "\033[0;31m",    // Red
            'blue' => "\033[0;34m",     // Blue
            'reset' => "\033[0m"        // Reset
        ];

        $color = $colors[$type] ?? $colors['info'];
        echo $color . $message . $colors['reset'] . PHP_EOL;
    }

    /**
     * Increment semantic version
     */    private function incrementVersion(string $version, string $type = 'patch'): string
    {
        // Remove 'v' if exists
        $version = ltrim($version, 'v');

        // Parse version
        $parts = explode('.', $version);
        $major = (int)($parts[0] ?? 0);
        $minor = (int)($parts[1] ?? 0);
        $patch = (int)($parts[2] ?? 0);

        switch ($type) {
            case 'major':
                $major++;
                $minor = 0;
                $patch = 0;
                break;
            case 'minor':
                $minor++;
                $patch = 0;
                break;
            case 'patch':
            default:
                $patch++;
                break;
        }

        return "v{$major}.{$minor}.{$patch}";
    }

    /**
     * Get latest tag from repository
     */
    private function getLatestTag(string $repoDir): string
    {
        $result = $this->exec('git describe --tags --abbrev=0', $repoDir);

        if ($result['success'] && !empty($result['output'])) {
            return trim($result['output'][0]);
        }
        return 'v0.0.0'; // Default tag if none exists
    }    /**
     * Check if there are changes in the repository
     */
    private function hasChanges(string $repoDir): bool
    {
        $result = $this->exec('git status --porcelain', $repoDir);
        
        if (!$result['success']) {
            $this->log("   ❌ Error checking git status:", 'error');
            foreach ($result['output'] as $line) {
                $this->log("      {$line}", 'error');
            }
            return false;
        }
        
        $changes = array_filter($result['output']);
        
        if (!empty($changes)) {
            $this->log("   📊 Detected changes:", 'blue');
            foreach ($changes as $change) {
                $this->log("      {$change}", 'blue');
            }
        }
        
        return !empty($changes);
    }
    /**
     * Update composer.json
     */
    private function updateComposerJson(string $filePath): bool
    {
        if (!file_exists($filePath)) {
            return false;
        }

        $content = file_get_contents($filePath);        // Make backup
        file_put_contents($filePath . '.backup', $content);

        // Update dependency
        $pattern = '/"condoedge\/utils":\s*"[^"]*"/';
        $replacement = '"condoedge/utils": "' . $this->latestUtilsVersion . '"';

        $newContent = preg_replace($pattern, $replacement, $content);

        if ($newContent !== $content) {
            file_put_contents($filePath, $newContent);
            return true;
        }

        // Remove backup if no changes
        unlink($filePath . '.backup');
        return false;
    }

    /**
     * Process an individual package
     */
    private function processPackage(string $packageDir): void
    {
        $packageName = basename($packageDir);
        if ($this->isProject($packageDir)) {
            $this->log("   🏗️  It's a PROJECT, skipping...", 'blue');
            $this->stats['projects']++;
            return; // ← HERE is where it skips and doesn't continue
        }

        $this->log(str_repeat('=', 50), 'blue');
        $this->log("📦 Processing: {$packageName}", 'warning');
        $this->log(str_repeat('=', 50), 'blue');
        $this->log("   📁 Directory: {$packageDir}");

        $this->stats['processed']++;

        // Check if it's a git repository
        if (!is_dir($packageDir . '/.git')) {
            $this->log("   ❌ Not a git repository, skipping...", 'error');
            return;
        }

        // Check if it's a git repository
        if (!is_dir($packageDir . '/.git')) {
            $this->log("   ❌ Not a git repository, skipping...", 'error');
            return;
        }

        // Check if git is available and working
        $gitCheck = $this->exec('git --version', $packageDir);
        if (!$gitCheck['success']) {
            $this->log("   ❌ Git not available or not working:", 'error');
            foreach ($gitCheck['output'] as $line) {
                $this->log("      {$line}", 'error');
            }
            return;
        }

        // Check composer.json
        $composerPath = $packageDir . '/composer.json';
        if (!file_exists($composerPath)) {
            $this->log("   ❌ No composer.json found, skipping...", 'error');
            return;
        }

        // Check if contains condoedge/utils
        $composerContent = file_get_contents($composerPath);
        if (strpos($composerContent, 'condoedge/utils') === false) {
            $this->log("   ⚠️  Doesn't contain condoedge/utils, skipping...", 'warning');
            return;
        }

        $this->log("   ✓ Valid git repository with condoedge/utils", 'success');

        // Get current branch
        $branchResult = $this->exec('git branch --show-current', $packageDir);
        $currentBranch = $branchResult['success'] ? trim($branchResult['output'][0]) : 'main';
        $this->log("   🌿 Current branch: {$currentBranch}");

        // Pull latest changes
        $this->log("   🔄 Pulling latest changes...", 'warning');
        $pullResult = $this->exec("git pull origin {$currentBranch}", $packageDir);

        if ($pullResult['success']) {
            $this->log("   ✓ Pull successful", 'success');
        } else {
            $this->log("   ❌ Error during pull, continuing...", 'error');
        }

        // Get latest tag
        $latestTag = $this->getLatestTag($packageDir);
        $this->log("   🏷️  Latest tag: {$latestTag}");

        // Update composer.json
        $this->log("   🔧 Updating condoedge/utils to {$this->latestUtilsVersion}...", 'warning');

        if (!$this->updateComposerJson($composerPath)) {
            $this->log("   ⚠️  composer.json didn't change", 'warning');
            return;
        }

        $this->log("   ✓ composer.json updated", 'success');

        // Show change
        $this->log("   📝 New dependency:", 'blue');
        $lines = file($composerPath);
        foreach ($lines as $lineNum => $line) {
            if (strpos($line, 'condoedge/utils') !== false) {
                $this->log("      " . trim($line));
                break;
            }
        }        // Check for changes
        if (!$this->hasChanges($packageDir)) {
            $this->log("   ⚠️  No changes to commit", 'warning');
            unlink($composerPath . '.backup');
            return;
        }

        $this->log("   ✓ There are changes to commit", 'success');        // Add files
        $this->log("   📁 Adding files to git...", 'warning');
        $addResult = $this->exec('git add composer.json', $packageDir);
        
        if (!$addResult['success']) {
            $this->log("   ❌ Error adding files to git:", 'error');
            foreach ($addResult['output'] as $line) {
                $this->log("      {$line}", 'error');
            }
            $this->stats['errors']++;
            return;
        }
        
        $this->log("   ✓ Files added successfully", 'success');

        // Verify files are staged
        $statusResult = $this->exec('git status --porcelain', $packageDir);
        if ($statusResult['success']) {
            $this->log("   📊 Git status after add:", 'blue');
            foreach ($statusResult['output'] as $line) {
                $this->log("      {$line}", 'blue');
            }
        }

        // Commit
        $commitMessage = "Update condoedge/utils to {$this->latestUtilsVersion}";
        $this->log("   💾 Committing: {$commitMessage}", 'warning');
        $commitResult = $this->exec("git commit -m \"{$commitMessage}\"", $packageDir);
        
        if (!$commitResult['success']) {
            $this->log("   ❌ Error committing:", 'error');
            foreach ($commitResult['output'] as $line) {
                $this->log("      {$line}", 'error');
            }
            $this->stats['errors']++;
            return;
        }

        // Increment version
        $newVersion = $this->incrementVersion($latestTag, 'patch');
        $this->log("   🏷️  New version: {$newVersion}");

        // Create tag
        $this->log("   🏷️  Creating tag {$newVersion}...", 'warning');
        $this->exec("git tag {$newVersion}", $packageDir);

        // Push commits and tags
        $this->log("   🚀 Pushing commits and tags...", 'warning');
        $pushCommits = $this->exec("git push origin {$currentBranch}", $packageDir);
        $pushTags = $this->exec("git push origin {$newVersion}", $packageDir);

        if ($pushCommits['success'] && $pushTags['success']) {
            $this->log("   🎉 Push successful! Version {$newVersion} published", 'success');
            $this->stats['updated']++;
        } else {
            $this->log("   ❌ Error during push", 'error');
            $this->stats['errors']++;
        }

        // Clean backup
        if (file_exists($composerPath . '.backup')) {
            unlink($composerPath . '.backup');
        }
    }

    /**
     * Find all repositories (optimized)
     */
    private function findRepositories(): array
    {
        $repositories = [];
        if (!is_dir($this->baseDir)) {
            throw new Exception("Directory {$this->baseDir} doesn't exist");
        }

        $this->log("🔍 Searching repositories in {$this->baseDir}...", 'warning');

        // Method 1: Use find command if available (much faster)
        $findResult = $this->exec("find \"{$this->baseDir}\" -maxdepth 3 -type d -name \".git\"");

        if ($findResult['success'] && !empty($findResult['output'])) {
            $this->log("✓ Using 'find' command (fast)", 'success');
            foreach ($findResult['output'] as $gitDir) {
                $gitDir = trim($gitDir);
                if (!empty($gitDir)) {
                    $repositories[] = dirname($gitDir);
                }
            }
            return $repositories;
        }

        // Method 2: Limited direct scan (only 2 levels deep)
        $this->log("⚠️  'find' command not available, using direct scan...", 'warning');

        // Scan first level
        $firstLevel = scandir($this->baseDir);
        foreach ($firstLevel as $item) {
            if ($item === '.' || $item === '..') continue;

            $fullPath = $this->baseDir . DIRECTORY_SEPARATOR . $item;
            if (!is_dir($fullPath)) continue;

            // Check if it's directly a git repo
            if (is_dir($fullPath . DIRECTORY_SEPARATOR . '.git')) {
                $repositories[] = $fullPath;
                continue;
            }

            // Scan second level only
            $secondLevel = @scandir($fullPath);
            if ($secondLevel === false) continue;

            foreach ($secondLevel as $subItem) {
                if ($subItem === '.' || $subItem === '..') continue;

                $subPath = $fullPath . DIRECTORY_SEPARATOR . $subItem;
                if (is_dir($subPath) && is_dir($subPath . DIRECTORY_SEPARATOR . '.git')) {
                    $repositories[] = $subPath;
                }
            }
        }

        return $repositories;
    }

    /**
     * Check dependencies (no composer since we don't use it)
     */    private function checkDependencies(): void
    {
        $this->log("🔍 Checking dependencies...", 'warning');

        // Only check git
        $result = $this->exec('git --version');
        if (!$result['success']) {
            throw new Exception("❌ Git is not installed or not in PATH");
        }

        $this->log("✅ Dependencies OK", 'success');
    }

    /**
     * Execute the complete process
     */    public function run(): void
    {
        $this->log("🚀 Starting massive package update", 'success');
        $this->log("📍 Base directory: {$this->baseDir}", 'warning');

        // Detect version first
        $this->detectLatestUtilsVersion();
        $this->log("🔧 New utils version: {$this->latestUtilsVersion}", 'warning');
        $this->log("⏰ " . date('Y-m-d H:i:s'), 'warning');

        try {
            // Check dependencies
            $this->checkDependencies();

            // Warning
            $this->log("\n⚠️  WARNING: This script will:", 'warning');
            $this->log("   • Update condoedge/utils to {$this->latestUtilsVersion} in all packages");
            $this->log("   • Commit the changes");
            $this->log("   • Create new tags (incrementing patch)");
            $this->log("   • Push commits and tags");

            echo "\nContinue? (y/N): ";
            $handle = fopen("php://stdin", "r");
            $line = fgets($handle);
            fclose($handle);

            if (strtolower(trim($line)) !== 'y') {
                $this->log("❌ Operation cancelled", 'warning');
                return;
            }

            // Find repositories
            $repositories = $this->findRepositories();
            $this->log("\n📊 Found " . count($repositories) . " repositories", 'blue');

            // Process each repository
            foreach ($repositories as $repo) {
                $this->processPackage($repo);
            }

            // Final statistics
            $this->log("\n🎊 Process completed!", 'success');
            $this->log("📊 Statistics:", 'warning');
            $this->log("   • Repositories processed: {$this->stats['processed']}");
            $this->log("   • Packages updated: {$this->stats['updated']}");
            $this->log("   • Errors: {$this->stats['errors']}");
            $this->log("   • Total time: " . date('Y-m-d H:i:s'));
        } catch (Exception $e) {
            $this->log("💥 Error: " . $e->getMessage(), 'error');
            exit(1);
        }
    }
}

// Execute if called directly
if (php_sapi_name() === 'cli') {
    // Check for command line arguments
    $baseDir = null;

    if ($argc > 1) {
        $baseDir = $argv[1];

        // Validate directory exists
        if (!is_dir($baseDir)) {
            echo "❌ Error: Directory '$baseDir' does not exist.\n";
            echo "Usage: php utils_updater.php [directory]\n";
            echo "Example: php utils_updater.php C:\\Users\\jkend\\Documents\\kompo\n";
            exit(1);
        }
    }

    $updater = new PackageUpdater($baseDir);
    $updater->run();
}
