import React from "react";
import { Button } from "./ui/button";
import { fetchApi } from "@/utils/api";
import { useAuth } from "@/contexts/AuthContext";
import { Trash2 } from "lucide-react";
import { toast } from "./ui/use-toast";
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "./ui/dialog";

type DeleteButtonProps = {
  id: number;
  type: string;
  onDeleteCard: (id: number) => void;
};

const handleDeleteFetch = async (
  id: number,
  type: string,
  accessToken: string
) => {
  const cards = type === "Quiz" ? "quizzes" : "decks";
  const response = await fetchApi(
    "DELETE",
    `${cards}/${id}`,
    null,
    accessToken
  );
  return response;
};

const DeleteButton = ({ id, type, onDeleteCard }: DeleteButtonProps) => {
  const { accessToken } = useAuth();
  const cards = type === "Quiz" ? "Quizz" : "Deck";

  const deleteHandler = async (event: React.MouseEvent<HTMLButtonElement>) => {
    event.stopPropagation();
    const response = await handleDeleteFetch(id, type, accessToken);
    if (response.status === 204) {
      onDeleteCard(id);
      toast({ description: "Card delete succesfully" });
    } else {
      toast({ description: response.message });
    }
  };

  return (
    <div>
      <Dialog>
        <DialogTrigger asChild>
          <Button
            variant="ghost"
            className="p-3 hover:bg-red-500"
            onClick={(event) => event.stopPropagation()}
          >
            <Trash2 size={14} />
          </Button>
        </DialogTrigger>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>Delete {cards}</DialogTitle>
            <DialogDescription>
              Are you sure you want to delete this {cards}?
            </DialogDescription>
          </DialogHeader>
          <DialogFooter className="sm:justify-end">
            <DialogClose asChild>
              <Button
                type="button"
                onClick={(event) => deleteHandler(event)}
                variant="destructive"
                className="w-1/2"
              >
                <div>Delete</div>
              </Button>
            </DialogClose>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
};

export default DeleteButton;
