<?php
namespace Application\ActionMenu;

use Laminas\Html\Div;
use Laminas\Html\Button;

class ActionMenu extends Div
{
    private $div;
    
    public function __construct()
    {
        $this->class = 'dropdown';
        
        $button = new Button();
        $button->id = 'dropdownMenuButton';
        $button->class = 'btn btn-secondary btn-sm dropdown-toggle';
        $button->label = "<i class='fas fa-tasks'></i>";
        $button->data_bs_toggle = 'dropdown';
        $button->aria_has_popup = 'true';
        $button->aria_expanded = 'false';
        
        $this->add($button);
        
        $div = new Div();
        $div->class = 'dropdown-menu';
        $div->aria_labeled_by = 'dropdownMenuButton';
        $this->div = $div;
        $this->add($div);
    }
    
    public function add_menu_item($menu_item)
    {
        foreach ($this->items as $item) {
            if (is_a($item, Div::class)) {
                $item->add($menu_item);
            }
        }
    }
}