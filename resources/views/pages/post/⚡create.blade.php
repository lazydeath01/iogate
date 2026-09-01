<?php

use Livewire\Component;

new class extends Component {
    public string $title = '';

    public string $content = '';

    public function save()
    {
        $this->validate([
            'title' => 'required|max:255',
            'content' => 'required',
        ]);

        dd($this->title, $this->content);
    }
};
?>

<div>
    <p class=" font-bold text-red-500 p-6 pl-12 bg-blue-900">Hello world</p>
</div>
