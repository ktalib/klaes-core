{{-- Progress Modal --}}
<div id="progressModal" class="fixed inset-0 bg-black/30 hidden items-center justify-center">
  <div class="bg-white border rounded-lg w-full max-w-md mx-4">
    <div class="p-6">
      <h3 id="progressTitle" class="text-xl font-bold">Working...</h3>
      <p id="progressSub" class="text-muted-foreground">Please wait</p>
    </div>
    <div class="px-6 pb-6">
      <div class="space-y-4">
        <div class="w-full bg-muted rounded-full h-2"><div class="progress-bar bg-indigo-600 h-2 rounded-full" style="width:0%"></div></div>
        <div id="progressText" class="text-sm text-center text-muted-foreground">0%</div>
        <div id="progressDone" class="flex items-center justify-center gap-2 text-green-700 hidden">
          <i class="fa-solid fa-circle-check"></i><span>Done!</span>
        </div>
      </div>
    </div>
  </div>
</div>