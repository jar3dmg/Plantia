import { request } from "./api-client.js";

// Adaptadores del dominio de sesión y preferencias.

export const getSession = () => request("status");
export const getPlantCatalog = () => request("plants");
export const authenticate = (mode, credentials) =>
  request(mode, { payload: credentials });
export const endSession = () => request("logout", { payload: {} });
export const saveOnboardingProfile = (profile) =>
  request("save_profile", { payload: profile });
export const savePreferences = (preferences) =>
  request("save_preferences", { payload: preferences });
