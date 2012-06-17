<?php
/**
 * GitPHP ProjectListDirectory
 *
 * Lists all projects in a given directory
 *
 * @author Christopher Han <xiphux@gmail.com>
 * @copyright Copyright (c) 2010 Christopher Han
 * @package GitPHP
 * @subpackage Git
 */

require_once(GITPHP_INCLUDEDIR . 'Config.class.php');
require_once(GITPHP_GITOBJECTDIR . 'projectlist/ProjectListBase.class.php');
require_once(GITPHP_GITOBJECTDIR . 'Project.class.php');

/**
 * ProjectListDirectory class
 *
 * @package GitPHP
 * @subpackage Git
 */
class GitPHP_ProjectListDirectory extends GitPHP_ProjectListBase
{

	/**
	 * exportedOnly
	 *
	 * Stores whether to only list exported projects
	 *
	 * @access protected
	 */
	protected $exportedOnly = false;

	/**
	 * __construct
	 *
	 * Constructor
	 *
	 * @access public
	 * @param string $projectRoot project root
	 * @param bool $exportedOnly whether to only allow exported projects
	 */
	public function __construct($projectRoot, $exportedOnly = false)
	{
		$this->exportedOnly = $exportedOnly;

		parent::__construct($projectRoot);

	}

	/**
	 * GetExportedOnly
	 *
	 * Gets whether this list only allows exported projects
	 *
	 * @access public
	 */
	public function GetExportedOnly()
	{
		return $this->exportedOnly;
	}
	
	/**
	 * PopulateProjects
	 *
	 * Populates the internal list of projects
	 *
	 * @access protected
	 */
	protected function PopulateProjects()
	{
		$this->RecurseDir(GitPHP_Util::AddSlash($this->projectRoot));
	}

	/**
	 * RecurseDir
	 *
	 * Recursively searches for projects
	 *
	 * @param string $dir directory to recurse into
	 */
	private function RecurseDir($dir)
	{
		if (!(is_dir($dir) && is_readable($dir)))
			return;

		GitPHP_DebugLog::GetInstance()->Log(sprintf('Searching directory %1$s', $dir));

		if ($dh = opendir($dir)) {
			$trimlen = strlen(GitPHP_Util::AddSlash($this->projectRoot)) + 1;
			while (($file = readdir($dh)) !== false) {
				$fullPath = $dir . '/' . $file;
				if ((strpos($file, '.') !== 0) && is_dir($fullPath)) {
					if (is_file($fullPath . '/HEAD')) {
						GitPHP_DebugLog::GetInstance()->Log(sprintf('Found project %1$s', $fullPath));
						$projectPath = substr($fullPath, $trimlen);
						if (!isset($this->projects[$projectPath])) {
							$project = $this->InstantiateProject($projectPath);
							if ($project) {
								$this->projects[$projectPath] = $project;
								unset($project);
							}
						}
					} else {
						$this->RecurseDir($fullPath);
					}
				} else {
					GitPHP_DebugLog::GetInstance()->Log(sprintf('Skipping %1$s', $fullPath));
				}
			}
			closedir($dh);
		}
	}

	/**
	 * InstantiateProject
	 *
	 * Instantiates project object
	 *
	 * @access protected
	 * @param string $proj project
	 * @return mixed project
	 */
	protected function InstantiateProject($proj)
	{
		try {

			$project = new GitPHP_Project($this->projectRoot, $proj);

			$category = trim(dirname($proj));
			if (!(empty($category) || (strpos($category, '.') === 0))) {
				$project->SetCategory($category);
			}

			if ($this->exportedOnly && !$project->GetDaemonEnabled()) {
				GitPHP_DebugLog::GetInstance()->Log(sprintf('Project %1$s not enabled for export', $project->GetPath()));
				return null;
			}

			$this->ApplyGlobalConfig($project);

			$this->ApplyGitConfig($project);

			if ($this->projectSettings && isset($this->projectSettings[$proj])) {
				$this->ApplyProjectSettings($project, $this->projectSettings[$proj]);
			}

			return $project;

		} catch (Exception $e) {
			GitPHP_DebugLog::GetInstance()->Log($e->getMessage());
		}

		return null;
	}

}