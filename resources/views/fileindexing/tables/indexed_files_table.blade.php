<div id="indexed-empty-state" class="rounded-md border p-8 text-center" style="display: none;">
	<div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-gray-100">
		<i data-lucide="file-text" class="h-6 w-6 text-gray-400"></i>
	</div>
	<h3 class="mb-2 text-lg font-medium">No indexed files yet</h3>
	<p class="mb-4 text-sm text-gray-500">
		Complete the indexing process to see files here
	</p>
	<button class="btn btn-primary gap-2" id="go-to-pending">
		Go to Pending Files
	</button>
</div>

<div id="indexed-table-container" class="rounded-md border overflow-x-auto">
	<div class="flex justify-between items-center p-4 border-b bg-gray-50">
		<div>
			<h4 class="text-sm font-medium text-gray-700">Indexed Files</h4>
			<p class="text-xs text-gray-500">View your indexed files (selection moved to Tracking Sheet)</p>
		</div>
	</div>

	<table id="indexed-files-table" class="min-w-full text-sm text-left">
		<thead class="bg-gray-50">
			<tr class="border-b">
				<th class="p-3 font-medium text-gray-600 uppercase text-xs tracking-wide">Tracking ID</th>
				<th class="p-3 font-medium text-gray-600 uppercase text-xs tracking-wide">Shelf/Rack</th>
				<th class="p-3 font-medium text-gray-600 uppercase text-xs tracking-wide">Registry</th>
				<th class="p-3 font-medium text-gray-600 uppercase text-xs tracking-wide">Registry Batch No</th>
				<th class="p-3 font-medium text-gray-600 uppercase text-xs tracking-wide">Sys Batch No</th>
				<th class="p-3 font-medium text-gray-600 uppercase text-xs tracking-wide">MDC Batch No</th>
				<th class="p-3 font-medium text-gray-600 uppercase text-xs tracking-wide">Group</th>
				<th class="p-3 font-medium text-gray-600 uppercase text-xs tracking-wide">File No</th>
				<th class="p-3 font-medium text-gray-600 uppercase text-xs tracking-wide">File Name</th>
				<th class="p-3 font-medium text-gray-600 uppercase text-xs tracking-wide">Plot No</th>
				<th class="p-3 font-medium text-gray-600 uppercase text-xs tracking-wide">Indexed Date</th>
				<th class="p-3 font-medium text-gray-600 uppercase text-xs tracking-wide">Indexed By</th>
				<th class="p-3 font-medium text-gray-600 uppercase text-xs tracking-wide">TP No</th>
				<th class="p-3 font-medium text-gray-600 uppercase text-xs tracking-wide">LPKN No</th>
				<th class="p-3 font-medium text-gray-600 uppercase text-xs tracking-wide">Land Use</th>
				<th class="p-3 font-medium text-gray-600 uppercase text-xs tracking-wide">District</th>
				<th class="p-3 font-medium text-gray-600 uppercase text-xs tracking-wide">LGA</th>
				<th class="p-3 font-medium text-gray-600 uppercase text-xs tracking-wide">Status</th>
				<th class="p-3 font-medium text-gray-600 uppercase text-xs tracking-wide text-center">Actions</th>
			</tr>
		</thead>
		<tbody></tbody>
	</table>
</div>
