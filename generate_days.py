import json

months = {
    1: 31, 2: 29, 3: 31, 4: 30, 5: 31, 6: 30,
    7: 31, 8: 31, 9: 30, 10: 31, 11: 30, 12: 31
}

words = [
    "Pizza", "Pancake", "Puppy", "Space", "Dinosaur", "Cookie", "Ice Cream", 
    "Bubble", "Superhero", "Robot", "Unicorn", "Chocolate", "Video Game", "Ninja", 
    "Pajama", "Silly Hat", "Lego", "Treehouse", "Bicycle", "Kite", "Popcorn",
    "Magic", "Dolphin", "Pirate", "Dragon", "Banana", "Robot", "Star", "Moon",
    "Gummy Bear", "Cupcake", "Elephant", "Monkey", "Penguin", "Tiger", "Bear",
    "Rocket", "Racecar", "Train", "Airplane", "Boat", "Submarine", "Castle"
]

days = {}
import random
random.seed(42)

for m in range(1, 13):
    days[str(m)] = {}
    for d in range(1, months[m] + 1):
        if m == 2 and d == 9:
            days[str(m)][str(d)] = "National Pizza Day"
        elif m == 5 and d == 4:
            days[str(m)][str(d)] = "Star Wars Day"
        elif m == 9 and d == 19:
            days[str(m)][str(d)] = "Talk Like a Pirate Day"
        elif m == 10 and d == 4:
            days[str(m)][str(d)] = "National Taco Day"
        else:
            word = random.choice(words)
            days[str(m)][str(d)] = f"National {word} Day"

with open("storage/app/national_days.json", "w") as f:
    json.dump(days, f)

print("Generated national_days.json")
