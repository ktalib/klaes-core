/**
 * Caveat Data and Core Functionality
 * Contains mock data, state variables, and core functions
 */

// Encumbrance type descriptions mapping
const encumbranceDescriptions = {
    'mortgage': 'A legal interest placed by a lender as security for a loan.',
    'lien': 'A right to retain property until a debt owed is settled.',
    'charge': 'A registered financial claim or interest over the property.',
    'leasehold-interest': 'Where a property is leased to another party, limiting the rights of the freeholder.',
    'sub-lease': 'Further lease interests carved out of the primary lease.',
    'easement': 'A right for a third party to use part of the property (e.g., access, passage).',
    'court-order': 'A judicial restriction on property transactions.',
    'pending-litigation': 'A notice that the property is subject to an ongoing court case.',
    'power-of-attorney': 'Where legal authority is granted to another party to act on property matters.',
    'caution': 'A warning entered to restrict dealings until lifted.',
    'dispute-investigation': 'Where the property is under review by DCIV or another regulatory unit.',
    'deed-assignment': 'Restriction until transfer registration is finalized.',
    'probate': 'Restrictions pending estate administration or inheritance claim.',
    'government-acquisition': 'If the land falls under acquisition or designated government use.',
    'unpaid-charges': 'Encumbrance for outstanding ground rent, service charges, or development levies.'
};

// Mock caveats data
let mockCaveats = [
    {
        id: "1",
        caveatNumber: "CAV-2024-001",
        encumbranceType: "Mortgage",
        typeOfDeed: "Assignment",
        fileNumber: "RES-2024-001",
        location: "Plot 123, Block A, Kano Layout",
        petitioner: "First Bank Nigeria Ltd",
        grantee: "Musa Ali",
        regParticulars: {
            serialNo: "1",
            pageNo: "1",
            volumeNo: "2",
        },
        startDate: "2024-01-15",
        instructions: "Mortgage for property acquisition loan",
        remarks: "Standard bank mortgage",
        status: "active",
        createdBy: "Officer A",
        dateCreated: "2024-01-15T09:30:00Z",
    },
    {
        id: "2",
        caveatNumber: "CAV-2024-002",
        encumbranceType: "Caution",
        typeOfDeed: "Conveyance",
        fileNumber: "COM-2024-001",
        location: "Plot 456, Block B, Kano Layout",
        petitioner: "Ministry of Housing",
        grantee: "Jane Smith",
        regParticulars: {
            serialNo: "1",
            pageNo: "1",
            volumeNo: "8",
        },
        startDate: "2024-02-10",
        releaseDate: "2024-02-25",
        instructions: "Caution pending documentation review",
        remarks: "Released after verification",
        status: "released",
        createdBy: "Officer B",
        dateCreated: "2024-02-10T14:20:00Z",
    },
    {
        id: "3",
        caveatNumber: "CAV-2024-003",
        encumbranceType: "Court Order",
        typeOfDeed: "Transfer",
        fileNumber: "CON-COM-2019-296",
        location: "Plot 789, Block C, Kano Layout",
        petitioner: "High Court of Kano",
        grantee: "Robert Johnson",
        regParticulars: {
            serialNo: "1",
            pageNo: "1",
            volumeNo: "4",
        },
        startDate: "2024-03-05",
        instructions: "Court restraining order pending litigation",
        remarks: "Pending court resolution",
        status: "active",
        createdBy: "Officer C",
        dateCreated: "2024-03-05T16:15:00Z",
    },
    {
        id: "4",
        caveatNumber: "CAV-2024-004",
        encumbranceType: "Charge",
        typeOfDeed: "Lease",
        fileNumber: "RES-2024-002",
        location: "Plot 321, Block D, Kano Layout",
        petitioner: "Unity Bank Plc",
        grantee: "Sarah Williams",
        regParticulars: {
            serialNo: "1",
            pageNo: "1",
            volumeNo: "12",
        },
        startDate: "2024-03-20",
        instructions: "Bank charge for overdraft facility",
        remarks: "Active charge on property",
        status: "active",
        createdBy: "Officer D",
        dateCreated: "2024-03-20T11:45:00Z",
    },
    {
        id: "5",
        caveatNumber: "CAV-2024-005",
        encumbranceType: "Lien",
        typeOfDeed: "Power of Attorney",
        fileNumber: "COM-2024-002",
        location: "Plot 654, Block E, Kano Layout",
        petitioner: "Construction Company Ltd",
        grantee: "David Brown",
        regParticulars: {
            serialNo: "1",
            pageNo: "1",
            volumeNo: "15",
        },
        startDate: "2024-04-01",
        releaseDate: "2024-04-20",
        instructions: "Contractor's lien for unpaid work",
        remarks: "Lifted after payment settlement",
        status: "released",
        createdBy: "Officer E",
        dateCreated: "2024-04-01T08:30:00Z",
    }
];

// State variables - make them available globally
var caveats = [...mockCaveats];
var searchTerm = "";
var statusFilter = "all";
var selectedCaveat = null;
var activeTab = "place";
var isLoading = false;
var fileNumberMode = "selector";

// Form data object
const formData = {
    encumbranceType: "",
    typeOfDeed: "",
    fileNumber: "",
    location: "",
    petitioner: "",
    grantee: "",
    serialNo: "",
    pageNo: "",
    volumeNo: "",
    registrationNumber: "",
    startDate: "",
    endDate: "",
    instructions: "",
    remarks: "",
    liftingDate: "",
    liftInstructions: "",
    liftRemarks: ""
};

// Tab management functions
function setActiveTab(tabName) {
    console.log('setActiveTab called with:', tabName);
    activeTab = tabName;
    
    // Update triggers - remove old active classes and add new active class
    document.querySelectorAll('.tab-trigger').forEach(trigger => {
        trigger.classList.remove('border-blue-500', 'text-blue-600', 'bg-blue-50', 'active');
        trigger.classList.add('border-transparent', 'text-gray-500');
    });
    
    const activeTrigger = document.querySelector(`[data-tab="${tabName}"]`);
    if (activeTrigger) {
        activeTrigger.classList.remove('border-transparent', 'text-gray-500');
        activeTrigger.classList.add('active');
        console.log('Updated active trigger for tab:', tabName);
    } else {
        console.error('Active trigger not found for tab:', tabName);
    }
    
    // Show/hide content - using the correct HTML structure
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.remove('active');
        content.style.display = 'none';
    });
    
    const activeContent = document.getElementById(`tab-${tabName}`);
    if (activeContent) {
        activeContent.classList.add('active');
        activeContent.style.display = 'block';
        console.log('Activated tab content for:', tabName);
        
        // Render appropriate content when tab is activated
        if (tabName === 'log') {
            console.log('Log tab activated, rendering caveats table');
            if (typeof window.renderCaveatsTable === 'function') {
                window.renderCaveatsTable();
            } else {
                console.error('renderCaveatsTable function not available');
            }
        } else if (tabName === 'place') {
            console.log('Place tab activated, rendering caveats list');
            if (typeof window.renderCaveatsList === 'function') {
                window.renderCaveatsList();
            }
        } else if (tabName === 'lift') {
            console.log('Lift tab activated, rendering active caveats list');
            if (typeof window.renderActiveCaveatsList === 'function') {
                window.renderActiveCaveatsList();
            }
        }
    } else {
        console.error('Active content not found for tab:', tabName);
    }
}

function initializeTabs() {
    console.log('initializeTabs called');
    
    const tabTriggers = document.querySelectorAll('.tab-trigger');
    console.log('Found tab triggers:', tabTriggers.length);
    
    tabTriggers.forEach((trigger, index) => {
        const tabName = trigger.getAttribute('data-tab');
        console.log(`Setting up trigger ${index}: ${tabName}`);
        
        trigger.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('Tab clicked:', tabName);
            setActiveTab(tabName);
        });
    });
    
    // Set default active tab
    setActiveTab('place');
}

// Utility functions
function updateCaveatNumber() {
    const numberInput = document.getElementById('caveat-number');
    if (numberInput) {
        const timestamp = Date.now();
        const caveatNumber = `CV-${timestamp.toString().slice(-8)}`;
        numberInput.value = caveatNumber;
        formData.caveatNumber = caveatNumber;
    }
}

function updateDateCreated() {
    const dateInput = document.getElementById('date-created');
    if (dateInput) {
        const now = new Date();
        const formattedDate = now.toISOString().slice(0, 16);
        dateInput.value = formattedDate;
        formData.dateCreated = formattedDate;
    }
}

function setDefaultStartDate() {
    const startDateInput = document.getElementById('start-date');
    const releaseDateInput = document.getElementById('release-date');

    if (startDateInput) {
        // Set start date if empty
        if (!startDateInput.value) {
            const now = new Date();
            const formattedDate = now.toISOString().slice(0, 16);
            startDateInput.value = formattedDate;
            formData.startDate = formattedDate;
        }
        
        // Always calculate and set release date - 6 months after start date
        if (releaseDateInput && startDateInput.value) {
            const startDate = new Date(startDateInput.value);
            if (!isNaN(startDate)) {
                const r = new Date(startDate);
                r.setMonth(r.getMonth() + 6);
                const formattedRelease = r.toISOString().slice(0, 16);
                releaseDateInput.value = formattedRelease;
                formData.releaseDate = formattedRelease;
            }
        }
    }
}

// Toast notification function
function showToast(message, type = 'info') {
    const toast = document.getElementById('toast');
    const toastTitle = document.getElementById('toast-title');
    const toastDescription = document.getElementById('toast-description');
    const toastIcon = document.getElementById('toast-icon');
    
    if (!toast || !toastTitle || !toastDescription || !toastIcon) return;
    
    // Set title and description
    toastTitle.textContent = type === 'error' ? 'Error' : type === 'success' ? 'Success' : 'Info';
    toastDescription.textContent = message;
    
    // Set icon based on type
    toastIcon.className = 'mr-3';
    if (type === 'error') {
        toastIcon.classList.add('fa-solid', 'fa-exclamation-triangle', 'text-red-500');
    } else if (type === 'success') {
        toastIcon.classList.add('fa-solid', 'fa-check-circle', 'text-green-500');
    } else {
        toastIcon.classList.add('fa-solid', 'fa-info-circle', 'text-blue-500');
    }
    
    // Remove existing border classes
    toast.classList.remove('border-red-500', 'border-green-500', 'border-blue-500');
    
    // Add appropriate border based on type
    if (type === 'error') {
        toast.classList.add('border-red-500');
    } else if (type === 'success') {
        toast.classList.add('border-green-500');
    } else {
        toast.classList.add('border-blue-500');
    }
    
    // Show toast
    toast.classList.remove('hidden');
    
    // Hide after 5 seconds
    setTimeout(() => {
        toast.classList.add('hidden');
        toast.classList.remove('border-red-500', 'border-green-500', 'border-blue-500');
    }, 5000);
}

// Export global variables and functions
window.caveats = caveats;
window.searchTerm = searchTerm;
window.statusFilter = statusFilter;
window.selectedCaveat = selectedCaveat;
window.activeTab = activeTab;
window.isLoading = isLoading;
window.fileNumberMode = fileNumberMode;
window.formData = formData;
window.encumbranceDescriptions = encumbranceDescriptions;

window.setActiveTab = setActiveTab;
window.initializeTabs = initializeTabs;
window.updateCaveatNumber = updateCaveatNumber;
window.updateDateCreated = updateDateCreated;
window.setDefaultStartDate = setDefaultStartDate;
window.showToast = showToast;
