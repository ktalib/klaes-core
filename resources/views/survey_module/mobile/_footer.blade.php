<footer class="mobile-footer">
    <nav class="mobile-footer-inner" aria-label="Survey mobile navigation">
        <a href="{{ route('survey-module.mobile.register') }}" class="mobile-tab {{ $activeTab === 'register' ? 'active' : '' }}" @if($activeTab === 'register') aria-current="page" @endif>
            <i class="fas fa-pen-to-square" aria-hidden="true"></i><span>Register</span>
        </a>
        <a href="{{ route('survey-module.mobile.index') }}" class="mobile-tab {{ $activeTab === 'cases' ? 'active' : '' }}" @if($activeTab === 'cases') aria-current="page" @endif>
            <i class="fas fa-folder-open" aria-hidden="true"></i><span>Cases</span>
        </a>
        <button type="button" class="mobile-tab" onclick="ui.menu()" aria-label="Account menu">
            <i class="fas fa-circle-user" aria-hidden="true"></i><span>Profile</span>
        </button>
    </nav>
</footer>
