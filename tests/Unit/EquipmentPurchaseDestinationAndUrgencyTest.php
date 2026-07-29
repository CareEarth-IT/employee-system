<?php

namespace Tests\Unit;

use App\Models\EquipmentPurchaseApplication;
use PHPUnit\Framework\TestCase;

class EquipmentPurchaseDestinationAndUrgencyTest extends TestCase
{
    public function test_purchase_urgencies_include_no_shipping_asap(): void
    {
        $this->assertArrayHasKey(
            EquipmentPurchaseApplication::URGENCY_NO_SHIPPING_ASAP,
            EquipmentPurchaseApplication::PURCHASE_URGENCIES,
        );
        $this->assertSame(
            '送料はかからなくて、急ぎの購入希望',
            EquipmentPurchaseApplication::PURCHASE_URGENCIES[EquipmentPurchaseApplication::URGENCY_NO_SHIPPING_ASAP],
        );
    }

    public function test_item_destinations_include_onsite(): void
    {
        $this->assertArrayHasKey(
            EquipmentPurchaseApplication::DESTINATION_ONSITE,
            EquipmentPurchaseApplication::ITEM_DESTINATIONS,
        );
        $this->assertSame(
            '現場',
            EquipmentPurchaseApplication::ITEM_DESTINATIONS[EquipmentPurchaseApplication::DESTINATION_ONSITE],
        );
    }

    public function test_food_delivery_destinations_use_store_labels(): void
    {
        $this->assertSame(
            '桃谷店',
            EquipmentPurchaseApplication::DELIVERY_DESTINATIONS[EquipmentPurchaseApplication::DELIVERY_FOOD_MOMOTANI],
        );
        $this->assertSame(
            '物流センター',
            EquipmentPurchaseApplication::DELIVERY_DESTINATIONS[EquipmentPurchaseApplication::DELIVERY_FOOD_LOGISTICS],
        );
    }

    public function test_item_destination_label_includes_onsite_name(): void
    {
        $application = new EquipmentPurchaseApplication([
            'item_destination' => EquipmentPurchaseApplication::DESTINATION_ONSITE,
            'onsite_name' => '○○マンション新築工事',
        ]);

        $this->assertSame('現場（○○マンション新築工事）', $application->itemDestinationLabel());
        $this->assertSame('現場（○○マンション新築工事）', $application->listDepartmentLabel());
    }

    public function test_purchase_urgency_label_for_no_shipping_asap(): void
    {
        $application = new EquipmentPurchaseApplication([
            'purchase_urgency' => EquipmentPurchaseApplication::URGENCY_NO_SHIPPING_ASAP,
        ]);

        $this->assertSame(
            '送料はかからなくて、急ぎの購入希望',
            $application->purchaseUrgencyLabel(),
        );
    }
}
