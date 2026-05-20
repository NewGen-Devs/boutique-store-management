/**
 * i18n.js - Global Translation Service
 */
const I18N_DATA = {
    en: {
        // Navbar
        nav_features: "Features",
        nav_about: "About",
        nav_contact: "Contact",
        nav_login: "Log in",

        // Dashboard Common
        db_overview: "Overview",
        db_inventory: "Inventory",
        db_branches: "Branches",
        db_users: "Users",
        db_pos: "Point of Sale",
        db_logout: "Sign Out",
        db_products: "Products",
        db_reports: "Analytics",
        db_stock: "Stock",
        db_stock_ops: "Alerts",
        db_my_sales: "Performance",
        db_items: "Items",

        // Welcome
        welcome_title: "Welcome back",
        welcome_subtitle: "Here's what's happening today",

        // Dashboard Stats
        stat_revenue: "Total Revenue",
        stat_transactions: "Transactions",
        stat_products: "Products",
        stat_branches: "Active Branches",
        stat_month_revenue: "Month Revenue",
        stat_users: "Active Users",

        // Login
        login_subtitle: "Sign in to your account",
        login_btn: "Log in",
        login_back: "Back to Homepage",
    },
    am: { // Amharic
        nav_features: "ባህሪያት",
        nav_about: "ስለ እኛ",
        nav_contact: "ያግኙን",
        nav_login: "ግባ",

        db_overview: "አጠቃላይ እይታ",
        db_inventory: "እቃ ዝርዝር",
        db_branches: "ቅርንጫፎች",
        db_users: "ተጠቃሚዎች",
        db_pos: "መሸጫ ቦታ",
        db_logout: "ውጣ",
        db_products: "ምርቶች",
        db_reports: "ትንታኔ",
        db_stock: "ክምችት",
        db_stock_ops: "ማስጠንቀቂያዎች",
        db_my_sales: "አፈጻጸም",
        db_items: "ቃዎች",

        welcome_title: "እንኳን ደህና መጡ",
        welcome_subtitle: "ዛሬ ምን እየተከናወነ እንደሆነ ይመልከቱ",

        stat_revenue: "ጠቅላላ ገቢ",
        stat_transactions: "ግብይቶች",
        stat_products: "ምርቶች",
        stat_branches: "ንቁ ቅርንጫፎች",
        stat_month_revenue: "የወር ገቢ",
        stat_users: "ንቁ ተጠቃሚዎች",

        login_subtitle: "ወደ መለያዎ ይግቡ",
        login_btn: "ግባ",
        login_back: "ወደ ዋናው ገጽ ተመለስ",
    }
};

class I18nService {
    constructor() {
        this.currentLang = localStorage.getItem('lang') || 'en';
        this.init();
    }

    init() {
        document.addEventListener('DOMContentLoaded', () => {
            this.translatePage();
            this.setupLanguageSelectors();
        });
    }

    setLanguage(lang) {
        if (I18N_DATA[lang]) {
            this.currentLang = lang;
            localStorage.setItem('lang', lang);
            this.translatePage();
            // Dispatch event for components that need manual refresh
            window.dispatchEvent(new CustomEvent('languageChanged', { detail: lang }));
        }
    }

    translatePage() {
        const elements = document.querySelectorAll('[data-i18n]');
        elements.forEach(el => {
            const key = el.getAttribute('data-i18n');
            if (I18N_DATA[this.currentLang][key]) {
                if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') {
                    el.placeholder = I18N_DATA[this.currentLang][key];
                } else {
                    el.textContent = I18N_DATA[this.currentLang][key];
                }
            }
        });

        // Update HTML lang attribute
        document.documentElement.lang = this.currentLang;
    }

    setupLanguageSelectors() {
        const selectors = document.querySelectorAll('.lang-selector');
        selectors.forEach(sel => {
            sel.value = this.currentLang;
            sel.addEventListener('change', (e) => this.setLanguage(e.target.value));
        });
    }
}

window.I18N_DATA = I18N_DATA;
window.i18n = new I18nService();
