<?php if (! defined('BASEPATH')) exit('No direct script access allowed');

final class Parityerrors extends CI_Controller
{
    public function index(): void
    {
        $this->html404();
    }

    public function html404(): void
    {
        show_404('parity/error/404', FALSE);
    }

    public function htmlgeneral(): void
    {
        show_error('Synthetic parity error.', 500, 'Parity General Error');
    }

    public function htmlexception(): void
    {
        if (is_cli()) {
            load_class('Exceptions', 'core')->show_exception(new RuntimeException('Synthetic parity exception.'));
            exit(1);
        }
        throw new RuntimeException('Synthetic parity exception.');
    }

    public function htmlphp(): void
    {
        load_class('Exceptions', 'core')->show_php_error(E_USER_WARNING, 'Synthetic parity PHP error.', __FILE__, __LINE__);
        if (is_cli()) {
            exit(1);
        }
    }

    public function htmldb(): void
    {
        $this->db->query('SELECT * FROM __parity_missing_table');
    }
}
