<?php

use Livewire\Component;
use App\Models\PersonType;

new class extends Component {
    public $personTypes = [];
    public $nameInput = '';
    public $descriptionInput = '';
    public function mount()
    {
        $this->personTypes = PersonType::all();
    }

    public function createPersonType()
    {
        $this->validate([
            'nameInput' => 'required|max:100',
            'descriptionInput' => 'max:255',
        ]);
        $personType = PersonType::create([
            'name' => $this->nameInput,
            'description' => $this->descriptionInput,
        ]);
        $this->personTypes[] = $personType;
        $this->reset(['nameInput', 'descriptionInput']);
    }
};
?>

<div class="min-h-full bg-slate-100 p-4 sm:p-6">
    <div class="mx-auto flex max-w-6xl flex-col gap-5">
        <div class="flex items-end justify-between gap-4">
            <div>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">Vai trò</h1>
                <p class="mt-1 max-w-2xl text-sm text-slate-500">Tạo nhóm người để áp dụng quyền ra vào chi tiết cho từng
                    nhóm.</p>
            </div>
        </div>
        <div
            class="grid overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm lg:grid-cols-[minmax(19rem,0.8fr)_minmax(0,1.2fr)]">
            <section class="border-b border-slate-200 bg-slate-50 lg:border-b-0 lg:border-r">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="font-semibold text-slate-900">Tạo vai trò mới</h2>
                    <p class="mt-1 text-sm text-slate-500">Đặt tên rõ ràng để dễ phân quyền sau này.</p>
                </div>
                <div class="flex flex-col gap-5 p-5">
                    <form wire:submit.prevent="createPersonType" class="flex flex-col gap-5">
                        <label for="nameInput" class="flex flex-col gap-2 text-sm font-medium text-slate-700">
                            <span>Tên vai trò</span>
                            <input wire:model="nameInput" id="nameInput" type="text" placeholder="Ví dụ: Cán bộ"
                                class="rounded-md border border-slate-300 bg-white px-3 py-2 text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20">
                            @error('nameInput')
                                <span class="font-normal text-red-600">{{ $message }}</span>
                            @enderror
                        </label>
                        <label for="desInput" class="flex flex-col gap-2 text-sm font-medium text-slate-700">
                            <span>Ghi chú <span class="font-normal text-slate-400">(không bắt buộc)</span></span>
                            <textarea id="desInput" wire:model="descriptionInput" rows="3" placeholder="Mô tả ngắn về nhóm người này"
                                class="resize-none rounded-md border border-slate-300 bg-white px-3 py-2 text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20"></textarea>
                            @error('descriptionInput')
                                <span class="font-normal text-red-600">{{ $message }}</span>
                            @enderror
                        </label>
                        <button type="submit"
                            class="inline-flex w-full items-center justify-center rounded-md bg-teal-700 px-4 py-2.5 font-semibold text-white transition hover:bg-teal-800 focus:outline-none focus:ring-2 focus:ring-teal-600 focus:ring-offset-2">
                            Tạo vai trò
                        </button>
                    </form>
                </div>
            </section>

            <section class="min-w-0">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                    <div class="flex flex-row justify-between items-center w-full">
                        <div>
                            <h2 class="font-semibold text-slate-900">Danh sách vai trò</h2>
                            <p class="mt-1 text-sm text-slate-500">Các nhóm người đã được tạo trong hệ thống.</p>
                        </div>
                        <div
                            class="hidden rounded-md border border-slate-200 bg-white px-2 py-2 text-right shadow-sm sm:block">
                            <p class=" font-bold text-slate-900">{{ $personTypes->count() }} <span
                                    class="text-xs font-medium text-slate-500">đơn vị</span></p>
                        </div>
                    </div>
                    <span
                        class="rounded-full bg-teal-50 px-2.5 py-1 text-xs font-semibold text-teal-700 sm:hidden">{{ $personTypes->count() }}</span>
                </div>
                @if ($personTypes->isEmpty())
                    <div class="flex min-h-64 flex-col items-center justify-center px-5 py-10 text-center">
                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-xl text-slate-400"
                            aria-hidden="true">+</div>
                        <h3 class="mt-4 font-semibold text-slate-900">Chưa có vai trò nào</h3>
                        <p class="mt-1 max-w-xs text-sm text-slate-500">Tạo vai trò đầu tiên bằng biểu mẫu bên cạnh để
                            bắt đầu phân quyền.</p>
                    </div>
                @else
                    <div class="grid gap-3 p-4 sm:grid-cols-2">
                        @foreach ($personTypes as $item)
                            <article wire:key="person-type-{{ $item->id }}"
                                class="rounded-md border border-slate-200 bg-white p-4 transition hover:border-teal-300 hover:shadow-sm">
                                <div class="flex items-start gap-3">
                                    <div class="min-w-0">
                                        <h3 class="font-semibold text-slate-900">{{ $item->name }}</h3>
                                        @if ($item->description)
                                            <p class="mt-1 text-sm leading-5 text-slate-500">{{ $item->description }}
                                            </p>
                                        @else
                                            <p class="mt-1 text-sm italic text-slate-400">Chưa có ghi chú</p>
                                        @endif
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    </div>
</div>
