<div class="modal-dialog shadow-none" role="document">
    <div class="max-w-2xl mx-auto">
        <div class="bg-white rounded-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-lg font-medium text-gray-900">{{ __('Edit Department') }}</h3>
                <button type="button" class="text-gray-400 hover:text-gray-500 absolute top-4 right-4" data-dismiss="modal" aria-label="Close">
                    <span class="sr-only">Close</span>
                    <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {{ Form::model($department, ['route' => ['departments.update', $department->id], 'method' => 'PUT']) }}
            <div class="p-6 overflow-y-auto max-h-[60vh]">
                <div class="mb-4">
                    {{ Form::label('name', __('Department Name'), ['class' => 'block text-sm font-medium text-gray-700 mb-1']) }}
                    {{ Form::text('name', null, [
                        'class' => 'w-full p-2 border border-gray-300 rounded-md text-sm',
                        'placeholder' => __('Enter Department Name'),
                        'required' => 'required'
                    ]) }}
                </div>
                <div class="mb-4">
                    {{ Form::label('code', __('Department Code'), ['class' => 'block text-sm font-medium text-gray-700 mb-1']) }}
                    {{ Form::text('code', null, [
                        'class' => 'w-full p-2 border border-gray-300 rounded-md text-sm',
                        'placeholder' => __('Enter Department Code'),
                        'required' => 'required'
                    ]) }}
                </div>
                <div class="mb-4">
                    {{ Form::label('description', __('Description'), ['class' => 'block text-sm font-medium text-gray-700 mb-1']) }}
                    {{ Form::textarea('description', null, [
                        'class' => 'w-full p-2 border border-gray-300 rounded-md text-sm',
                        'placeholder' => __('Enter Description'),
                        'rows' => 3
                    ]) }}
                </div>
                <div class="flex items-center mb-4">
                    {{ Form::checkbox('is_active', 1, null, ['class' => 'h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500', 'id' => 'is_active']) }}
                    {{ Form::label('is_active', __('Active'), ['class' => 'ml-2 block text-sm text-gray-900']) }}
                </div>
            </div>
            <div class="px-6 py-3 bg-gray-50 text-right">
                {{ Form::submit(__('Update'), ['class' => 'inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500']) }}
                <button type="button" class="ml-2 inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" data-dismiss="modal">
                    {{ __('Cancel') }}
                </button>
            </div>
            {{ Form::close() }}
        </div>
    </div>
</div>
