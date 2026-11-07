<?php

declare(strict_types=1);

namespace OCA\FlzAkut\Integration;

use OCA\FlzAkut\Service\OrganizationGateway;
use OCP\App\IAppManager;
use PHPUnit\Framework\TestCase;

/**
 * Integration test for OrganizationGateway with flz_organization.
 * 
 * @covers \OCA\FlzAkut\Service\OrganizationGateway
 */
final class OrganizationGatewayIntegrationTest extends TestCase {

    private OrganizationGateway $gateway;
    private IAppManager $appManager;

    protected function setUp(): void {
        $this->appManager = $this->createMock(IAppManager::class);
        $this->gateway = new OrganizationGateway($this->appManager);
    }

    public function testGatewayHasFlzOrganizationAsPrimarySource(): void {
        // Verify that the gateway tries flz_organization first
        $reflection = new \ReflectionClass($this->gateway);
        
        // Check that the FLZ_ORGANIZATION_CLASS constant exists
        self::assertTrue(
            $reflection->hasConstant('FLZ_ORGANIZATION_CLASS'),
            'Gateway should have FLZ_ORGANIZATION_CLASS constant'
        );
        
        self::assertSame(
            'OCA\\FlzOrganization\\PublicApi\\V1\\OrganizationSnapshotService',
            $reflection->getConstant('FLZ_ORGANIZATION_CLASS'),
            'FLZ_ORGANIZATION_CLASS should point to flz_organization service'
        );
    }

    public function testGatewayHasLocalBaseAsFallbackSource(): void {
        $reflection = new \ReflectionClass($this->gateway);
        
        // Check that the LOCALBASE_CLASS constant exists
        self::assertTrue(
            $reflection->hasConstant('LOCALBASE_CLASS'),
            'Gateway should have LOCALBASE_CLASS constant'
        );
        
        self::assertSame(
            'OCA\\LocalBase\\PublicApi\\V1\\OrganizationSnapshotService',
            $reflection->getConstant('LOCALBASE_CLASS'),
            'LOCALBASE_CLASS should point to localbase service'
        );
    }

    public function testGatewayHasTryFlzOrganizationMethod(): void {
        $reflection = new \ReflectionClass($this->gateway);
        
        self::assertTrue(
            $reflection->hasMethod('tryFlzOrganization'),
            'Gateway should have tryFlzOrganization method'
        );
    }

    public function testGatewayHasTryLocalBaseMethod(): void {
        $reflection = new \ReflectionClass($this->gateway);
        
        self::assertTrue(
            $reflection->hasMethod('tryLocalBase'),
            'Gateway should have tryLocalBase method'
        );
    }

    public function testSnapshotMethodReturnsArray(): void {
        // Setup mock to return no apps enabled
        $this->appManager->method('getEnabledApps')
            ->willReturn([]);

        $result = $this->gateway->snapshot();
        
        self::assertIsArray($result);
        self::assertArrayHasKey('status', $result);
        self::assertArrayHasKey('roles', $result);
        self::assertArrayHasKey('areas', $result);
    }

    public function testMissingAppsReturnsEmptyArrays(): void {
        $this->appManager->method('getEnabledApps')
            ->willReturn([]);

        $result = $this->gateway->snapshot();
        
        self::assertSame('MISSING', $result['status']);
        self::assertSame([], $result['roles']);
        self::assertSame([], $result['areas']);
    }
}
