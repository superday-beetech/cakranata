import api from "@/api/client";

import type {
  CreateOrganizationUnitPayload,
  UpdateOrganizationUnitPayload,
} from "../types/organizationUnit";

const BASE_URL = "/organization/organization-units";

const organizationUnitService = {
  getAll(organizationId: number) {
    return api.post(`${BASE_URL}/index`,
        {
            organization_id: organizationId
        });
  },

  getUnit(organizationId: number, unitId: number) {
  return api.post(`${BASE_URL}/show-units`, {
    organization_id: organizationId,
    unit_id: unitId,
  });
},

  createUnit(
    organizationId: number,
    payload: CreateOrganizationUnitPayload
  ) {
    return api.post(`${BASE_URL}/create-units/${organizationId}`, payload);
  },

  updateUnit(
    organizationId: number,
    unitId: number,
    payload: UpdateOrganizationUnitPayload
  ) {
    return api.put(
      `${BASE_URL}/update-units/${organizationId}/${unitId}`,
      payload
    );
  },

  deactivateUnit(organizationId: number, unitId: number) {
    return api.delete(
      `${BASE_URL}/delete-units/${organizationId}/${unitId}`
    );
  },
};

export default organizationUnitService;
