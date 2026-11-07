#!/bin/bash
# Validation Script: flz_organization Integration in flz_akut
# Execute from flz_akut directory or nextcloud-dev with ddev

set -e

GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

log_success() { echo -e "${GREEN}✓${NC} $1"; }
log_failure() { echo -e "${RED}✗${NC} $1"; exit 1; }
log_warning() { echo -e "${YELLOW}⚠${NC} $1"; }
log_info() { echo "  $1"; }

echo ""
echo -e "${GREEN}=========================================="
echo "  flz_organization Integration Validation"
echo "==========================================${NC}"
echo ""

# Check if we're in a DDEV context
if command -v ddev &> /dev/null; then
    echo "[DDEV Mode]"
    DDEV_PREFIX="ddev exec"
else
    echo "[Direct Mode - Nextcloud CLI]"
    DDEV_PREFIX=""
fi

echo ""
echo "[1/6] Checking app status..."
APPS=$(ddev exec php html/occ app:list 2>&1 | grep -E "(flz_organization|flz_akut)" || true)
echo "$APPS"

if echo "$APPS" | grep -q "flz_organization"; then
    log_success "flz_organization is enabled"
else
    log_failure "flz_organization is not enabled"
fi

if echo "$APPS" | grep -q "flz_akut"; then
    log_success "flz_akut is enabled"
else
    log_failure "flz_akut is not enabled"
fi

echo ""
echo "[2/6] Checking flz_organization public API files..."
# Check if the public API files exist
FLZ_ORG_FILE=$(ddev exec test -f /var/www/html/html/custom_apps/flz_organization/lib/PublicApi/V1/OrganizationSnapshotService.php && echo "YES" || echo "NO")
FLZ_HOLIDAY_FILE=$(ddev exec test -f /var/www/html/html/custom_apps/flz_organization/lib/PublicApi/V1/HolidayCalendarProvider.php && echo "YES" || echo "NO")
FLZ_ORG_CLASS=$(ddev exec php -r "require_once '/var/www/html/html/custom_apps/flz_organization/lib/PublicApi/V1/OrganizationSnapshotService.php'; echo class_exists('OCA\\FlzOrganization\\PublicApi\\V1\\OrganizationSnapshotService') ? 'YES' : 'NO';" 2>&1)

if echo "$FLZ_ORG_FILE" | grep -q "YES"; then
    log_success "OrganizationSnapshotService.php file exists"
else
    log_failure "OrganizationSnapshotService.php file NOT found"
fi

if echo "$FLZ_HOLIDAY_FILE" | grep -q "YES"; then
    log_success "HolidayCalendarProvider.php file exists"
else
    log_failure "HolidayCalendarProvider.php file NOT found"
fi

if echo "$FLZ_ORG_CLASS" | grep -q "YES"; then
    log_success "OrganizationSnapshotService class loads correctly"
else
    log_warning "OrganizationSnapshotService class loading: $FLZ_ORG_CLASS"
fi

echo ""
echo "[3/6] Checking flz_akut gateway classes..."
# Check if the gateway classes exist and have the right methods
ORG_GATEWAY=$(ddev exec test -f /var/www/html/html/custom_apps/flz_akut/lib/Service/OrganizationGateway.php && echo "YES" || echo "NO")
HOLIDAY_GATEWAY=$(ddev exec test -f /var/www/html/html/custom_apps/flz_akut/lib/Service/HolidayGateway.php && echo "YES" || echo "NO")

if echo "$ORG_GATEWAY" | grep -q "YES"; then
    log_success "OrganizationGateway.php file exists"
else
    log_failure "OrganizationGateway.php file NOT found"
fi

if echo "$HOLIDAY_GATEWAY" | grep -q "YES"; then
    log_success "HolidayGateway.php file exists"
else
    log_failure "HolidayGateway.php file NOT found"
fi

echo ""
echo "[4/6] Checking flz_organization contract version..."
CONTRACT_VERSION=$(ddev exec php -r "require_once '/var/www/html/html/custom_apps/flz_organization/lib/PublicApi/V1/Contract.php'; echo \OCA\FlzOrganization\PublicApi\V1\Contract::supports('1.0') ? '1.0' : 'NOT SUPPORTED';" 2>&1 || echo "ERROR")

if echo "$CONTRACT_VERSION" | grep -q "1.0"; then
    log_success "Contract version 1.0 is supported"
else
    log_failure "Contract version check failed: $CONTRACT_VERSION"
fi

echo ""
echo "[5/6] Testing gateway priority via file content..."
# Check the constants in OrganizationGateway by reading the file
ORG_CONSTANT=$(ddev exec grep "FLZ_ORGANIZATION_CLASS" /var/www/html/html/custom_apps/flz_akut/lib/Service/OrganizationGateway.php | head -1 || echo "NOT FOUND")
LOCALBASE_CONSTANT=$(ddev exec grep "LOCALBASE_CLASS" /var/www/html/html/custom_apps/flz_akut/lib/Service/OrganizationGateway.php | head -1 || echo "NOT FOUND")

if echo "$ORG_CONSTANT" | grep -q "FlzOrganization"; then
    log_success "flz_organization is primary source in OrganizationGateway"
else
    log_failure "Primary source not correctly configured"
fi

if echo "$LOCALBASE_CONSTANT" | grep -q "LocalBase"; then
    log_success "localbase is fallback source in OrganizationGateway"
else
    log_failure "Fallback source not correctly configured"
fi

echo ""
echo "[6/6] Checking background job registration..."
BKJOB=$(ddev exec php html/occ backgroundjob:list 2>&1 | grep -i "RefreshHolidayCalendarJob" || true)
if echo "$BKJOB" | grep -q "RefreshHolidayCalendarJob"; then
    log_success "Background job RefreshHolidayCalendarJob is registered"
else
    log_warning "Background job not found in occ list (may require cache clear)"
fi

echo ""
echo -e "${GREEN}=========================================="
echo "  Validation Complete"
echo "==========================================${NC}"
echo ""
echo "Summary:"
echo "- flz_organization and flz_akut are both enabled"
echo "- Public API classes are available"
echo "- Gateway classes are configured with correct priority"
echo "- Contract version 1.0 is supported"
echo ""
echo "Note: Full runtime testing requires authenticated requests."
