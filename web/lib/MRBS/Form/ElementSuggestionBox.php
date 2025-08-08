<?php
namespace MRBS\Form;

class ElementSuggestionBox extends Element
{
    public function __construct()
    {
        parent::__construct('div');
        $this->setAttribute('id', 'participants_suggestions');
        $this->setAttribute('class', 'suggestion-box hidden');
        $this->setAttribute('style', 'position: absolute; background: white; z-index: 999; width: 350px; max-height: 150px; overflow-y: auto;');
    }

    public function render(): string
    {
        return '<div ' . $this->renderAttributes() . '></div>';
    }
}
