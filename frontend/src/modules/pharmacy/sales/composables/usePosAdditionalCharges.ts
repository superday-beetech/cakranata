import type { PosAdditionalCharge } from "../types/sales";

type ChargeType = PosAdditionalCharge["type"];

interface UsePosAdditionalChargesOptions {
  addAdditionalCharge: (charge: PosAdditionalCharge) => void;
  updateAdditionalCharge: (id: string, patch: Partial<PosAdditionalCharge>) => void;
  removeAdditionalCharge: (id: string) => void;
  showError: (error: unknown) => void;
}

export function usePosAdditionalCharges(options: UsePosAdditionalChargesOptions) {
  let chargeSequence = 0;

  function addCharge(): void {
    chargeSequence++;

    try {
      options.addAdditionalCharge({
        id: `charge-${chargeSequence}`,
        type: "other",
        label: "Biaya lainnya",
        description: "",
        amount: 0,
      });
    } catch (error) {
      options.showError(error);
    }
  }

  function updateCharge(id: string, field: "type" | "description" | "amount", value: string | number): void {
    try {
      switch (field) {
        case "amount":
          options.updateAdditionalCharge(id, {
            amount: Math.max(0, Number(value) || 0),
          });
          break;

        case "description":
          options.updateAdditionalCharge(id, {
            description: String(value),
          });
          break;

        case "type":
          options.updateAdditionalCharge(id, {
            type: value as ChargeType,
          });
          break;
      }
    } catch (error) {
      options.showError(error);
    }
  }

  function removeCharge(id: string): void {
    try {
      options.removeAdditionalCharge(id);
    } catch (error) {
      options.showError(error);
    }
  }

  return {
    addCharge,
    updateCharge,
    removeCharge,
  };
}
