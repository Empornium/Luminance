<?php

enforce_login();
authorize();

if (!check_perms('admin_manage_site_options')) error(403);
switch ($_POST['submit']) {
    case 'Save Site Options':
        $Validate = new Luminance\Legacy\Validate;

        // Load the site options table
        $currentOptions = $master->options->getAll();

        // Populate the new values into the SiteOptions array
        // and prepare the validation class
        foreach($currentOptions as $name => $option) {
            switch ($option['type']) {
                case 'bool':
                    $SiteOptionsUpdate[$name] = ($_POST[$name] ? true : false);
                    $Validate->SetFields($name, true, $option['type'], "Input error: $name contains invalid input", $option['validation']);
                    break;

                case 'enum':
                    $SiteOptionsUpdate[$name] = $_POST[$name];
                    $Validate->SetFields($name, true, 'inarray', "Input error: $name contains invalid input", $option['validation']);
                    break;

                case 'date':
                    $d = new \DateTime($_POST[$name]);
                    $SiteOptionsUpdate[$name] = $d->format('Y-m-d H:i:s');
                    $Validate->SetFields($name, true, 'date', "Input error: $name contains invalid input", $option['validation']);
                    break;

                default:
                    $SiteOptionsUpdate[$name] = $_POST[$name];
                    $Validate->SetFields($name, true, $option['type'], "Input error: $name contains invalid input", $option['validation']);
            }
        }

        // Validate
        $Err = $Validate->ValidateForm($SiteOptionsUpdate);
        if ($Err) error($Err);

        // Update the ORM
        foreach($currentOptions as $name => $currentOption) {
            $master->options->$name = $SiteOptionsUpdate[$name];
        }
        break;

    case 'Reset Site Options':
        $master->options->reset();
        break;
}
header('Location: tools.php?action=site_options');
