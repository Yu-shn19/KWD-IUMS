import { useContext, createContext } from "react";

export const StorageContext = createContext(null);

export const useStorage = () => {
  return useContext(StorageContext);
};