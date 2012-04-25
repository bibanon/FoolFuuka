<?php

if (!defined('BASEPATH'))
	exit('No direct script access allowed');


class System extends Admin_Controller
{


	function __construct()
	{
		parent::__construct();

		// only admins should do this
		$this->tank_auth->is_admin() or redirect('admin');

		// we need the upgrade module's functions
		$this->load->model('upgrade_model');

		// page title
		$this->viewdata['controller_title'] = '<a href="' . site_url("admin/system") . '">' . _("System") . '</a>';
	}

	/*
	 * A page telling if there's an ugrade available
	 *
	 * @author Woxxy
	 */


	function index()
	{
		redirect('/admin/system/information');
	}


	function information()
	{
		$this->viewdata["function_title"] = _("Information");

		// get current version from database
		$data["current_version"] = FOOL_VERSION;
		$data["form_title"] = _("Information");

		$this->viewdata["main_content_view"] = $this->load->view("admin/system/information",
			$data, TRUE);
		$this->load->view("admin/default.php", $this->viewdata);
	}


	function preferences()
	{
		$this->viewdata["function_title"] = _("Preferences");

		$form = array();

		if (find_imagick())
		{
			$imagick_status = '<span class="label label-success">' . _('Found and Working') . '</span>';
		}
		else
		{
			// @todo update the imagick statuses to match bootstrap 2.0
			if (!$this->fs_imagick->exec)
				$imagick_status = '<span class="label label-important">' . _('Not Available') . '</span><a rel="popover-right" href="#" data-content="' . htmlspecialchars(_('You must have Safe Mode turned off and the exec() function enabled to allow ImageMagick to process your images. Please check the information panel for more details.')) . '" data-original-title="' . htmlspecialchars(_('Disabled Functions')) . '"><img src="' . icons(388,
						16) . '" class="icon icon-small"></a>';
			else if (!$this->fs_imagick->found)
				$imagick_status = '<span class="label label-important">' . _('Not Found') . '</span><a rel="popover-right" href="#" data-content="' . htmlspecialchars(_('You must provide the correct path to the "convert" binary on your system. This is typically located under /usr/bin (Linux), /opt/local/bin (Mac OSX) or the installation directory (Windows).')) . '" data-original-title="' . htmlspecialchars(_('Disabled Functions')) . '"><img src="' . icons(388,
						16) . '" class="icon icon-small"></a>';
			else if (!$this->fs_imagick->available)
				$imagick_status = '<span class="label label-important">' . _('Not Working') . '</span><a rel="popover-right" href="#" data-content="' . htmlspecialchars(sprintf(_('There has been an error encountered when testing your ImageMagick installation. To manually check for errors, access your server via shell or command line and type: %s'),
							'<br/><code>' . $this->fs_imagick->found . ' -version</code>')) . '" data-original-title="' . htmlspecialchars(_('Disabled Functions')) . '"><img src="' . icons(388,
						16) . '" class="icon icon-small"></a>';
		}

		$form['open'] = array(
			'type' => 'open'
		);

		$form['fs_serv_imagick_path'] = array(
			'type' => 'input',
			'label' => _('Path to ImageMagick') . ' ' . $imagick_status,
			'placeholder' => '/usr/bin',
			'preferences' => 'fs_gen',
			'help' => _('The location of your ImageMagick "convert" executable')
		);
		
		$form['fs_sys_subdomain'] = array(
			'type' => 'input',
			'array' => TRUE,
			'label' => _('CDN subdomains'),
			'preferences' => TRUE,
			'help' => _('Insert an alternative base URL to FoOlFuuka to separate the system functions from the boards.')
		);

		$form['separator-2'] = array(
			'type' => 'separator'
		);

		$form['submit'] = array(
			'type' => 'submit',
			'value' => _('Submit'),
			'class' => 'btn btn-primary'
		);

		$form['close'] = array(
			'type' => 'close'
		);

		$data["form_title"] = _("Preferences");

		$this->submit_preferences_auto($form);

		$data['form'] = $form;

		// create a form
		$this->viewdata["main_content_view"] = $this->load->view("admin/form_creator",
			$data, TRUE);
		$this->load->view("admin/default", $this->viewdata);
	}



	function upgrade()
	{
		if($this->input->post('upgrade'))
		{
			// triggers the upgrade
			if (!$this->upgrade_model->do_upgrade())
			{
				// clean the cache in case of failure
				$this->upgrade_model->clean();
				// show some kind of error
				log_message('error', 'system.php do_upgrade(): failed upgrade');
				flash_notice('error', _('Upgrade failed: check file permissions.'));
			}
			else
			{
				flash_notice('success', _('Upgrade successful'));
			}
			
			redirect($this->uri->uri_string());
		}
		
		$this->viewdata["function_title"] = _("Upgrade");

		// get current version from constant
		$data["current_version"] = FOOL_VERSION;

		// check if the user can upgrade by checking if files are writeable
		$data["can_upgrade"] = $this->upgrade_model->check_files();
		if (!$data["can_upgrade"])
		{
			// if there are not writeable files, suggest the actions to take
			$this->upgrade_model->permissions_suggest();
		}

		// look for the latest version available
		$data["new_versions"] = $this->upgrade_model->check_latest();

		// we're going to use markdown here
		$this->load->library('Markdown_Parser');
		$data["changelog"] = $this->upgrade_model->get_changelog();

		// print out
		$this->viewdata["main_content_view"] = $this->load->view("admin/system/upgrade",
			$data, TRUE);
		$this->load->view("admin/default.php", $this->viewdata);
	}

}
