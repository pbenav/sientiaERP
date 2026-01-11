#!/bin/bash
#
# nexERP Setup Script
# Quick setup for both backend and TUI client
#

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKEND_DIR="$SCRIPT_DIR/backend"
TUI_DIR="$SCRIPT_DIR/tui-client"

echo "╔═══════════════════════════════════════════════════════════════╗"
echo "║                    nexERP Setup Script                        ║"
echo "╚═══════════════════════════════════════════════════════════════╝"
echo

# Check for required commands
echo "Checking dependencies..."

if ! command -v php &> /dev/null; then
    echo "❌ PHP is not installed. Please install PHP 8.3 or higher."
    exit 1
fi
echo "✓ PHP $(php -r 'echo PHP_VERSION;')"

if ! command -v composer &> /dev/null; then
    echo "❌ Composer is not installed. Please install Composer."
    exit 1
fi
echo "✓ Composer installed"

if ! command -v curl &> /dev/null; then
    echo "❌ curl is not installed. Please install curl."
    exit 1
fi
echo "✓ curl installed"

if ! command -v jq &> /dev/null; then
    echo "⚠️  jq is not installed. TUI client requires jq."
    echo "   Install with: sudo apt-get install jq (Debian/Ubuntu)"
    echo "   Or: brew install jq (macOS)"
fi

echo

# Setup Backend
echo "Setting up Laravel Backend..."
cd "$BACKEND_DIR"

if [ ! -f ".env" ]; then
    echo "Creating .env file..."
    cp .env.example .env
fi

if [ ! -f "database/database.sqlite" ]; then
    echo "Creating SQLite database..."
    touch database/database.sqlite
fi

echo "Running migrations..."
php artisan migrate --force

echo "Seeding database with sample data..."
php artisan db:seed --force

echo "✓ Backend setup complete!"
echo

# Setup TUI Client
echo "Setting up TUI Client..."
cd "$TUI_DIR"

chmod +x nexerp-tui.sh
echo "✓ TUI Client setup complete!"
echo

# Final instructions
echo "╔═══════════════════════════════════════════════════════════════╗"
echo "║                    Setup Complete! 🎉                         ║"
echo "╚═══════════════════════════════════════════════════════════════╝"
echo
echo "To start nexERP:"
echo
echo "1. Start the backend server (in one terminal):"
echo "   cd backend"
echo "   php artisan serve"
echo
echo "2. Run the TUI client (in another terminal):"
echo "   ./tui-client/nexerp-tui.sh"
echo
echo "The backend will be available at: http://localhost:8000"
echo "The TUI client will connect to: http://localhost:8000/api"
echo
echo "Default sample products have been loaded into the database."
echo
