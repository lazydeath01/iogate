<?php

use Livewire\Component;

new class extends Component {
    public function addDepartment($data) {}
    public function updateDepartment($data) {}
};
?>

<div class=" flex flex-col items-center bg-amber-300 w-full h-full">
    Content
    <div class="w-[40vw] h-full bg-gray-200">
        <div id="items">
            <div class="bg-white py-2 px-1 border border-gray-200" id="item1">item 1</div>
            <div class="bg-white py-2 px-1 border border-gray-200" id="item2">item 2</div>
            <div class="bg-white py-2 px-1 border border-gray-200" id="item3">
                item 3
                <div class="nested-items"></div>
            </div>
            <div class="bg-white py-2 px-1 border border-gray-200" id="item4">item 4</div>
            <div class="bg-white py-2 px-1 border border-gray-200" id="item5">item 5</div>
            <div class="bg-white py-2 px-1 border border-gray-200" id="item6">item 6</div>
            <div class="bg-white py-2 px-1 border border-gray-200" id="item7">item 7</div>
            <div class="bg-white py-2 px-1 border border-gray-200" id="item8">item 8</div>
            <div class="bg-white py-2 px-1 border border-gray-200" id="item9">item 9</div>
            <div class="bg-white py-2 px-1 border border-gray-200" id="item10">item 10</div>
            <div class="bg-white py-2 px-1 border border-gray-200" id="item11">item 11</div>
            <div class="bg-white py-2 px-1 border border-gray-200" id="item12">item 12</div>
            <div class="bg-white py-2 px-1 border border-gray-200" id="item13">item 13</div>
            <div class="bg-white py-2 px-1 border border-gray-200" id="item14">item 14</div>
        </div>
    </div>
</div>

<script>

    </script>


<script>
    const el = document.getElementById('items');
    // for (let i = 0; i < el.children.length; i++) {
    //     new Sortable(el.children[i], {
    //         group: "nested",
    //         animation: 150,
    //         fallbackOnBody: true,
    //         swapThreshold: 0.65,
    //     });
    // }
    Sortable.create(el, {
        group: "nested",
        animation: 150,
        fallbackOnBody: true,
        swapThreshold: 0.65,
    });
</script>
